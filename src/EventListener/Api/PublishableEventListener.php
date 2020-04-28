<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener\Api;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventListener
{
    use ClassMetadataTrait;

    private PublishableHelper $publishableHelper;

    public function __construct(PublishableHelper $publishableHelper, ManagerRegistry $registry)
    {
        $this->publishableHelper = $publishableHelper;
        $this->initRegistry($registry);
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->publishableHelper->isPublishable($data) ||
            $request->isMethod(Request::METHOD_DELETE)
        ) {
            return;
        }

        $publishable = $this->checkMergeDraftIntoPublished($request, $data);
        $event->setControllerResult($publishable);
    }

    public function onPostRead(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->publishableHelper->isPublishable($data) ||
            !$request->isMethod(Request::METHOD_GET)
        ) {
            return;
        }

        $this->checkMergeDraftIntoPublished($request, $data, true);
    }

    public function onPostDeserialize(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->publishableHelper->isPublishable($data) ||
            !($request->isMethod(Request::METHOD_PUT) || $request->isMethod(Request::METHOD_PATCH))
        ) {
            return;
        }

        $configuration = $this->publishableHelper->getConfiguration($data);

        // User cannot change the publication date of the original resource
        if (
            true === $this->publishableHelper->isPublishedRequest($request) &&
            $this->getValue($request->attributes->get('previous_data'), $configuration->fieldName) !== $this->getValue($data, $configuration->fieldName)
        ) {
            throw new BadRequestHttpException('You cannot change the publication date of a published resource.');
        }
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        /** @var PublishableTrait|null $data */
        $data = $request->attributes->get('data');
        if (
            null === $data ||
            !$this->publishableHelper->isPublishable($data)
        ) {
            return;
        }
        $response = $event->getResponse();

        $configuration = $this->publishableHelper->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        $draftResource = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName) ?? $data;

        /** @var \DateTime|null $publishedAt */
        $publishedAt = $classMetadata->getFieldValue($draftResource, $configuration->fieldName);
        if (!$publishedAt || $publishedAt <= new \DateTime()) {
            return;
        }

        $response->setExpires($publishedAt);
    }

    private function getValue(object $object, string $property)
    {
        return $this->getClassMetadata($object)->getFieldValue($object, $property);
    }

    private function checkMergeDraftIntoPublished(Request $request, object $data, bool $flushDatabase = false): object
    {
        if (!$this->publishableHelper->isActivePublishedAt($data)) {
            return $data;
        }

        $configuration = $this->publishableHelper->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        $publishedResourceAssociation = $classMetadata->getFieldValue($data, $configuration->associationName);
        $draftResourceAssociation = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName);
        if (
            !$publishedResourceAssociation &&
            (!$draftResourceAssociation || !$this->publishableHelper->isActivePublishedAt($draftResourceAssociation))
        ) {
            return $data;
        }

        // the request is for a resource with an active publish date
        // either a draft, if so it may be a published version we need to replace with
        // or a published resource which may have a draft that has an active publish date
        $entityManager = $this->getEntityManager($data);
        /** @var ClassMetadataInfo $meta */
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
