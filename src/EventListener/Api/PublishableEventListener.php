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

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentBundle\Publishable\ClassMetadataTrait;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventListener
{
    use ClassMetadataTrait;

    private PublishableHelper $publishableHelper;
    private ManagerRegistry $registry;

    public function __construct(PublishableHelper $publishableHelper, ManagerRegistry $registry)
    {
        $this->publishableHelper = $publishableHelper;
        // not unused, used by the trait
        $this->registry = $registry;
    }

    public function onPreWrite(RequestEvent $event): void
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
        $classMetadata = $this->getClassMetadata($data);

        $publishedResource = $classMetadata->getFieldValue($data, $configuration->associationName);
        if ($publishedResource && $this->publishableHelper->isPublished($data)) {
            $entityManager = $this->getEntityManager($data);
            $entityManager->remove($publishedResource);
            $entityManager->flush();

            $meta = $entityManager->getClassMetadata(\get_class($data));
            $identifier = $meta->getSingleIdentifierFieldName();
            $publishedIdentifier = $classMetadata->getFieldValue($publishedResource, $identifier);
            $classMetadata->setFieldValue($data, $identifier, $publishedIdentifier);
            $classMetadata->setFieldValue($data, $configuration->associationName, null);
        }
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
            true === $request->query->getBoolean('published', false) &&
            $this->getValue($request->attributes->get('previous_data'), $configuration->fieldName) !== $this->getValue($data, $configuration->fieldName)
        ) {
            throw new BadRequestHttpException('You cannot change the publication date of a published resource.');
        }
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        /** @var PublishableTrait $data */
        $data = $request->attributes->get('data');
        if (
            empty($data) ||
            !$this->publishableHelper->isPublishable($data)
        ) {
            return;
        }
        $response = $event->getResponse();
        $response->setVary('Authorization');

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
}
