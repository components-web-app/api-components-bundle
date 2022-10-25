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
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

trait DoctrineResourceFlushTrait
{
    private array $pageDataPropertiesChanged = [];
    private PropertyAccessor $propertyAccessor;
    private ObjectRepository|EntityRepository $collectionRepository;
    private ObjectRepository|EntityRepository $positionRepository;
    private array $resourceIris = [];

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        ManagerRegistry $entityManager,
        private readonly ResourceChangedPropagatorInterface $resourceChangedPropagator,
        private readonly ResourceClassResolverInterface $resourceClassResolver
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->collectionRepository = $entityManager->getRepository(Collection::class);
        $this->positionRepository = $entityManager->getRepository(ComponentPosition::class);
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $object = $eventArgs->getObject();
        $this->collectUpdatedResource($object, 'updated');

        $changeSet = $eventArgs->getEntityChangeSet();
        $associationMappings = $this->getAssociationMappings($eventArgs->getObjectManager(), $eventArgs->getObject());

        if ($object instanceof PageDataInterface) {
            $this->pageDataPropertiesChanged = array_keys($changeSet);
        }

        foreach ($changeSet as $field => $value) {
            if (!isset($associationMappings[$field])) {
                continue;
            }

            $this->collectUpdatedResource($value[0], 'updated');
            $this->collectUpdatedResource($value[1], 'updated');
        }
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collectUpdatedResource($entity, 'created', $em, true);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collectUpdatedResource($entity, 'updated', $em, true);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collectUpdatedResource($entity, 'deleted', $em, true);
        }
    }

    public function postFlush(): void
    {
        $this->addResourcesToPurge($this->gatherResourcesForPositionsWithPageDataProperties(), 'updated');
        $this->addResourcesToPurge($this->gatherIrisForCollectionResources(), 'updated');
        $this->purgeResources();
    }

    private function gatherRelationResourceClasses(ObjectManager $em, $entity): void
    {
        $associationMappings = $this->getAssociationMappings($em, $entity);
        foreach (array_keys($associationMappings) as $property) {
            if ($this->propertyAccessor->isReadable($entity, $property)) {
                $value = $this->propertyAccessor->getValue($entity, $property);
                if ($value instanceof PersistentCollection) {
                    foreach ($value as $item) {
                        $this->collectUpdatedResource($item, 'updated');
                    }
                } else {
                    $this->collectUpdatedResource($value, 'updated');
                }
            }
        }
    }

    private function gatherResourcesForPositionsWithPageDataProperties(): array
    {
        $positionResources = [];
        foreach ($this->pageDataPropertiesChanged as $pageDataProperty) {
            $positions = $this->positionRepository->findBy([
                'pageDataProperty' => $pageDataProperty,
            ]);
            foreach ($positions as $position) {
                $positionResources[] = $position;
            }
        }

        return $positionResources;
    }

    private function gatherIrisForCollectionResources(): array
    {
        if (empty($this->resourceIris)) {
            return [];
        }

        $collectionResources = [];
        foreach ($this->resourceIris as $resourceIri) {
            $collections = $this->collectionRepository->findBy([
                'resourceIri' => $resourceIri,
            ]);
            foreach ($collections as $collection) {
                $collectionResources[] = $collection;
            }
        }

        $this->resourceIris = [];
        if (empty($collectionResources)) {
            return [];
        }

        return $collectionResources;
    }

    private function getAssociationMappings(ObjectManager $em, $entity): array
    {
        return $em->getClassMetadata(ClassUtils::getClass($entity))->getAssociationMappings();
    }

    private function collectUpdatedResource($resource, string $type, ?ObjectManager $em = null, bool $gatherRelated = false): void
    {
        if (!$resource) {
            return;
        }
        $this->addResourceIris([$resource], $type);
        $this->resourceChangedPropagator->collectItems([$resource], $type);
        if ($gatherRelated && $em) {
            $this->gatherRelationResourceClasses($em, $resource);
        }
    }

    private function addResourcesToPurge(array $resources, string $type): void
    {
        $this->addResourceIris($resources, $type);
        $this->resourceChangedPropagator->collectItems($resources, $type);
    }

    private function addResourceIris(array $resources, string $type): void
    {
        foreach ($resources as $resource) {
            try {
                $this->resourceChangedPropagator->collectResource($resource, $type);

                $resourceClass = $this->resourceClassResolver->getResourceClass($resource);
                $resourceIri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));

                if (!\in_array($resourceIri, $this->resourceIris, true)) {
                    $this->resourceIris[] = $resourceIri;
                }
            } catch (InvalidArgumentException $e) {
            }
        }
    }

    private function purgeResources(): void
    {
        $this->resourceChangedPropagator->propagate();
        $this->resourceIris = [];
    }
}
