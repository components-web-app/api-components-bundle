<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventListener
{
    use ApiEventListenerTrait;
    use ClassMetadataTrait;

    public const VALID_TO_PUBLISH_HEADER = 'valid-to-publish';
    public const VALID_PUBLISHED_QUERY = 'validate_published';

    private PublishableStatusChecker $publishableStatusChecker;
    private ValidatorInterface $validator;
    private PublishableAttributeReader $publishableAnnotationReader;

    public function __construct(PublishableStatusChecker $publishableStatusChecker, ManagerRegistry $registry, ValidatorInterface $validator)
    {
        $this->publishableAnnotationReader = $publishableStatusChecker->getAnnotationReader();
        $this->publishableStatusChecker = $publishableStatusChecker;
        $this->initRegistry($registry);
        $this->validator = $validator;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $this->getAttributes($request);
        if (
            empty($attributes['data']) ||
            !$this->publishableAnnotationReader->isConfigured($attributes['class']) ||
            $request->isMethod(Request::METHOD_DELETE) ||
            $attributes['operation'] instanceof CollectionOperationInterface
        ) {
            return;
        }

        $publishable = $this->checkMergeDraftIntoPublished($request, $attributes['data']);
        $event->setControllerResult($publishable);
    }

    public function onPostRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $this->getAttributes($request);
        if (
            empty($attributes['data']) ||
            !$this->publishableAnnotationReader->isConfigured($attributes['class']) ||
            !$request->isMethod(Request::METHOD_GET) ||
            $attributes['operation'] instanceof CollectionOperationInterface
        ) {
            return;
        }

        $this->checkMergeDraftIntoPublished($request, $attributes['data'], true);
    }

    public function onPostDeserialize(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $this->getAttributes($request);
        if (
            empty($attributes['data']) ||
            !$this->publishableAnnotationReader->isConfigured($attributes['class']) ||
            !($request->isMethod(Request::METHOD_PUT) || $request->isMethod(Request::METHOD_PATCH))
        ) {
            return;
        }

        $configuration = $this->publishableAnnotationReader->getConfiguration($attributes['class']);

        // User cannot change the publication date of the original resource
        if (
            true === $this->publishableStatusChecker->isPublishedRequest($request) &&
            $this->getValue($request->attributes->get('previous_data'), $configuration->fieldName) !== $this->getValue($attributes['data'], $configuration->fieldName)
        ) {
            throw new UnprocessableEntityHttpException('You cannot change the publication date of a published resource.');
        }
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        $attributes = $this->getAttributes($request);

        /**
         * @var PublishableTrait|null $data
         */
        $data = $attributes['data'];

        if (
            null === $data ||
            !$this->publishableAnnotationReader->isConfigured($attributes['class']) ||
            $attributes['operation'] instanceof CollectionOperationInterface
        ) {
            return;
        }
        $response = $event->getResponse();

        $configuration = $this->publishableAnnotationReader->getConfiguration($attributes['class']);
        $classMetadata = $this->getClassMetadata($attributes['class']);
        $draftResource = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName) ?? $data;

        // Add Expires HTTP header
        /** @var \DateTime|null $publishedAt */
        $publishedAt = $classMetadata->getFieldValue($draftResource, $configuration->fieldName);
        if ($publishedAt && $publishedAt > new \DateTime()) {
            $response->setExpires($publishedAt);
        }

        if (!$this->publishableStatusChecker->isGranted($attributes['class'])) {
            return;
        }

        if ($response->isClientError()) {
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '0');

            return;
        }

        // Force validation from querystring, and/or add validate-to-publish custom HTTP header
        try {
            $this->validator->validate($data, [PublishableValidator::PUBLISHED_KEY => true]);
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '1');
        } catch (ValidationException $exception) {
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '0');

            if (
                true === $request->query->getBoolean(self::VALID_PUBLISHED_QUERY, false) &&
                \in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT], true)
            ) {
                throw $exception;
            }
        }
    }

    private function getValue(object $object, string $property)
    {
        return $this->getClassMetadata($object)->getFieldValue($object, $property);
    }

    private function checkMergeDraftIntoPublished(Request $request, object $data, bool $flushDatabase = false): object
    {
        if (!$this->publishableStatusChecker->isActivePublishedAt($data)) {
            return $data;
        }

        $configuration = $this->publishableAnnotationReader->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        $publishedResourceAssociation = $classMetadata->getFieldValue($data, $configuration->associationName);
        $draftResourceAssociation = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName);
        if (
            !$publishedResourceAssociation &&
            (!$draftResourceAssociation || !$this->publishableStatusChecker->isActivePublishedAt($draftResourceAssociation))
        ) {
            return $data;
        }

        // the request is for a resource with an active publish date
        // either a draft, if so it may be a published version we need to replace with
        // or a published resource which may have a draft that has an active publish date
        $entityManager = $this->getEntityManager($data);

        $meta = $entityManager->getClassMetadata(\get_class($data));
        $identifierFieldName = $meta->getSingleIdentifierFieldName();

        if ($publishedResourceAssociation) {
            // retrieving a draft that is now published
            $draftResource = $data;
            $publishedResource = $publishedResourceAssociation;

            $publishedId = $classMetadata->getFieldValue($publishedResource, $identifierFieldName);
            $request->attributes->set('id', $publishedId);
            $request->attributes->set('data', $publishedResource);
            $request->attributes->set('previous_data', clone $publishedResource);
        } else {
            // retrieving a published resource and draft should now replace it
            $publishedResource = $data;
            $draftResource = $draftResourceAssociation;
        }

        $classMetadata->setFieldValue($publishedResource, $configuration->reverseAssociationName, null);
        $classMetadata->setFieldValue($draftResource, $configuration->associationName, null);

        $this->mergeDraftIntoPublished($identifierFieldName, $draftResource, $publishedResource, $flushDatabase);

        return $publishedResource;
    }

    private function mergeDraftIntoPublished(string $identifierFieldName, object $draftResource, object $publishedResource, bool $flushDatabase): void
    {
        $draftReflection = new \ReflectionClass($draftResource);
        $publishedReflection = new \ReflectionClass($publishedResource);
        $properties = $publishedReflection->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            if ($identifierFieldName === $name) {
                continue;
            }
            $draftProperty = $draftReflection->hasProperty($name) ? $draftReflection->getProperty($name) : null;
            if ($draftProperty) {
                $draftProperty->setAccessible(true);
                $draftValue = $draftProperty->getValue($draftResource);
                $property->setValue($publishedResource, $draftValue);
            }
        }

        $entityManager = $this->getEntityManager($draftResource);
        $entityManager->remove($draftResource);

        if ($flushDatabase) {
            $entityManager->flush();
        }
    }
}
