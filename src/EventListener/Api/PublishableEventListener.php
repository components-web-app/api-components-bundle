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
use Silverback\ApiComponentBundle\Annotation\Publishable;
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

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        /** @var PublishableTrait $data */
        $data = $request->attributes->get('data');
        if (!$this->publishableHelper->isPublishable($data)) {
            return;
        }
        $response = $event->getResponse();
        $response->setVary('Authorization');

        $configuration = $this->publishableHelper->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        $draftResource = $data->getDraftResource() ?? $data;

        /** @var \DateTime|null $publishedAt */
        $publishedAt = $classMetadata->getFieldValue($draftResource, $configuration->fieldName);
        if (!$publishedAt || $publishedAt <= new \DateTime()) {
            return;
        }

        $response->setExpires($publishedAt);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        if (empty($data) || !$this->publishableHelper->isPublishable($data)) {
            return;
        }

        $configuration = $this->publishableHelper->getConfiguration($data);
        $classMetadata = $this->getClassMetadata($data);

        switch ($request->getMethod()) {
            case Request::METHOD_POST:
                $this->handlePOSTRequest($classMetadata, $configuration, $data);
                break;
            case Request::METHOD_PATCH:
            case Request::METHOD_PUT:
                $this->handlePUTRequest($classMetadata, $configuration, $data, $request);
                break;
        }
    }

    private function handlePOSTRequest(ClassMetadataInfo $classMetadata, Publishable $configuration, object $data): void
    {
        // It's not possible for a user to define a resource as draft from another
        $classMetadata->setFieldValue($data, $configuration->associationName, null);

        // User doesn't have draft access: force publication date
        if (!$this->publishableHelper->isGranted()) {
            $classMetadata->setFieldValue($data, $configuration->fieldName, new \DateTimeImmutable());
        }
    }

    private function handlePUTRequest(ClassMetadataInfo $classMetadata, Publishable $configuration, object $data, Request $request): void
    {
        $changeSet = $this->getEntityManager($data)->getUnitOfWork()->getEntityChangeSet($data);

        // It's not possible to change the publishedResource property
        if (isset($changeSet[$configuration->associationName])) {
            $classMetadata->setFieldValue($data, $configuration->associationName, $changeSet[$configuration->associationName][0]);
        }

        // User doesn't have draft access: cannot change the publication date
        if (!$this->publishableHelper->isGranted()) {
            if (isset($changeSet[$configuration->fieldName])) {
                $classMetadata->setFieldValue($data, $configuration->fieldName, $changeSet[$configuration->fieldName][0]);
            }

            // Nothing to do here anymore for user without draft access
            return;
        }

        // User requested for original object
        if (true === $request->query->getBoolean('published', false)) {
            // User cannot change the publication date of the original resource
            if ($changeSet[$configuration->fieldName]) {
                throw new BadRequestHttpException('You cannot change the publication date of a published resource.');
            }

            // User wants to update the original object: nothing to do here anymore
            return;
        }

        // Resource is a draft of another resource: nothing to do here anymore
        if (null !== $classMetadata->getFieldValue($data, $configuration->associationName)) {
            return;
        }

        // Any field has been modified: create or update draft
        $draft = $this->getEntityManager($data)->getRepository($this->getObjectClass($data))->findOneBy([
            $configuration->associationName => $data,
        ]);
        if (!$draft) {
            // Identifier(s) should be reset from AbstractComponent::__clone method
            $draft = clone $data;

            // Add draft object to UnitOfWork
            $this->getEntityManager($draft)->persist($draft);

            // Empty publishedDate on draft
            $classMetadata->setFieldValue($draft, $configuration->fieldName, null);

            // Set publishedResource on draft
            $classMetadata->setFieldValue($draft, $configuration->associationName, $data);

            // Set draftResource on data
            $classMetadata->setFieldValue($data, $configuration->reverseAssociationName, $draft);
        }

        // Replace data by its draft
        $request->attributes->set('data', $draft);

        // Rollback modifications on original resource
        $this->getEntityManager($data)->refresh($data);
    }
}
