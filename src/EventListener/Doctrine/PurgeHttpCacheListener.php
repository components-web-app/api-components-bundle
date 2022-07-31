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
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
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
    private array $resourceIris = [];
    private array $tags = [];
    private array $pageDataPropertiesChanged = [];
    private PropertyAccessor $propertyAccessor;
    private ObjectRepository|EntityRepository $collectionRepository;
    private ObjectRepository|EntityRepository $positionRepository;

    public function __construct(PurgerInterface $purger, IriConverterInterface $iriConverter, ResourceClassResolverInterface $resourceClassResolver, ManagerRegistry $entityManager)
    {
        $this->purger = $purger;
        $this->iriConverter = $iriConverter;
        $this->resourceClassResolver = $resourceClassResolver;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();

        $this->collectionRepository = $entityManager->getRepository(Collection::class);
        $this->positionRepository = $entityManager->getRepository(ComponentPosition::class);
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

        if ($object instanceof PageDataInterface) {
            $this->pageDataPropertiesChanged = array_keys($changeSet);
        }

        foreach ($changeSet as $field => $value) {
            if (!isset($associationMappings[$field])) {
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
        $this->purgePositionsWithPageDataProperties();
        $this->purgeCollectionResources();
        $this->purgeTags();
    }

    private function purgePositionsWithPageDataProperties(): void
    {
        foreach ($this->pageDataPropertiesChanged as $pageDataProperty) {
            $positions = $this->positionRepository->findBy([
                'pageDataProperty' => $pageDataProperty,
            ]);
            $positionIris = [];
            foreach ($positions as $position) {
                $positionIris[] = $this->iriConverter->getIriFromResource($position);
            }
            $this->purger->purge($positionIris);
        }
    }

    private function purgeCollectionResources(): void
    {
        if (empty($this->resourceIris)) {
            return;
        }

        $collectionIris = [];
        foreach ($this->resourceIris as $resourceIri) {
            $collections = $this->collectionRepository->findBy([
                'resourceIri' => $resourceIri,
            ]);
            foreach ($collections as $collection) {
                $collectionIris[] = $this->iriConverter->getIriFromResource($collection);
            }
        }

        $this->resourceIris = [];
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
        if (null === $entity) {
            return;
        }

        try {
            $resourceClass = $this->resourceClassResolver->getResourceClass($entity);
            $resourceIri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
            if (!\in_array($resourceIri, $this->resourceIris, true)) {
                $this->resourceIris[] = $resourceIri;
            }
        } catch (OperationNotFoundException|InvalidArgumentException $e) {
        }
    }

    private function gatherRelationResourceClasses(EntityManagerInterface $em, $entity): void
    {
        $associationMappings = $this->getAssociationMappings($em, $entity);
        foreach (array_keys($associationMappings) as $property) {
            if ($this->propertyAccessor->isReadable($entity, $property)) {
                $value = $this->propertyAccessor->getValue($entity, $property);
                if ($value instanceof PersistentCollection) {
                    foreach ($value as $item) {
                        $this->addResourceClass($item);
                    }
                } else {
                    $this->addResourceClass($value);
                }
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
            $iri = $this->iriConverter->getIriFromResource($value);
            $this->tags[$iri] = $iri;
        } catch (InvalidArgumentException $e) {
        } catch (RuntimeException $e) {
        }
    }
}
