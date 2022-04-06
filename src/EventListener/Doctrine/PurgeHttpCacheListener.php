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

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\HttpCache\PurgerInterface;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Purges desired resources on when doctrine is flushed from the proxy cache.
 *
 * @author Daniel West <daniel@silverback.is>
 *
 * @experimental
 */
class PurgeHttpCacheListener
{
    private PurgerInterface $purger;
    private IriConverterInterface $iriConverter;
    private ResourceClassResolverInterface $resourceClassResolver;
    private array $resourceClasses = [];
    private array $tags = [];
    private PropertyAccessor $propertyAccessor;
    private ObjectRepository|EntityRepository $collectionRepository;

    public function __construct(PurgerInterface $purger, IriConverterInterface $iriConverter, ResourceClassResolverInterface $resourceClassResolver, ManagerRegistry $entityManager)
    {
        $this->purger = $purger;
        $this->iriConverter = $iriConverter;
        $this->resourceClassResolver = $resourceClassResolver;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        $this->collectionRepository = $entityManager->getRepository(Collection::class);
    }

    /**
     * Collects modified resources so we can check if any collection components need purging.
     *
     * Based on:
     *
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        $this->addResourceClass($object);

        $changeSet = $eventArgs->getEntityChangeSet();
        $associationMappings = $this->getAssociationMappings($eventArgs->getEntityManager(), $eventArgs->getObject());

        foreach ($changeSet as $key => $value) {
            if (!isset($associationMappings[$key])) {
                continue;
            }

            $this->addResourceClass($value[0]);
            $this->addResourceClass($value[1]);
        }
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->addResourceClass($entity);
            $this->gatherRelationResourceClasses($em, $entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->addResourceClass($entity);
            $this->gatherRelationResourceClasses($em, $entity);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->addResourceClass($entity);
            $this->gatherRelationResourceClasses($em, $entity);
        }
    }

    /**
     * Purges tags collected during this request, and clears the tag list.
     *
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    public function postFlush(): void
    {
        $this->purgeCollectionResources();
        $this->purgeTags();
    }

    private function purgeCollectionResources(): void
    {
        if (empty($this->resourceClasses)) {
            return;
        }

        $collectionIris = [];
        foreach ($this->resourceClasses as $resourceIri) {
            $collections = $this->collectionRepository->findBy([
                'resourceIri' => $resourceIri,
            ]);
            foreach ($collections as $collection) {
                $collectionIris[] = $this->iriConverter->getIriFromItem($collection);
            }
        }

        $this->resourceClasses = [];
        if (empty($collectionIris)) {
            return;
        }

        $this->purger->purge($collectionIris);
    }

    private function purgeTags(): void
    {
        if (empty($this->tags)) {
            return;
        }

        $this->purger->purge(array_values($this->tags));
        $this->tags = [];
    }

    private function addResourceClass($entity): void
    {
        try {
            $resourceClass = $this->iriConverter->getIriFromResourceClass($this->resourceClassResolver->getResourceClass($entity));
            if (!\in_array($resourceClass, $this->resourceClasses, true)) {
                $this->resourceClasses[] = $resourceClass;
            }
        } catch (OperationNotFoundException|InvalidArgumentException $e) {
        }
    }

    private function gatherRelationResourceClasses(EntityManagerInterface $em, $entity): void
    {
        $associationMappings = $this->getAssociationMappings($em, $entity);
        foreach (array_keys($associationMappings) as $property) {
            if ($this->propertyAccessor->isReadable($entity, $property)) {
                $this->addResourceClass($this->propertyAccessor->getValue($entity, $property));
            }
        }
    }

    private function getAssociationMappings(EntityManagerInterface $em, $entity): array
    {
        return $em->getClassMetadata(ClassUtils::getClass($entity))->getAssociationMappings();
    }

    /**
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    public function addTagsFor($value): void
    {
        if (!$value) {
            return;
        }

        if (!is_iterable($value)) {
            $this->addTagForItem($value);

            return;
        }

        if ($value instanceof PersistentCollection) {
            $value = clone $value;
        }

        foreach ($value as $v) {
            $this->addTagForItem($v);
        }
    }

    /**
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    private function addTagForItem($value): void
    {
        try {
            $iri = $this->iriConverter->getIriFromItem($value);
            $this->tags[$iri] = $iri;
        } catch (InvalidArgumentException $e) {
        } catch (RuntimeException $e) {
        }
    }
}
