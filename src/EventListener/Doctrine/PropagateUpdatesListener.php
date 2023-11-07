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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class PropagateUpdatesListener
{
    private PropertyAccessor $propertyAccessor;
    private ObjectRepository|EntityRepository $collectionRepository;
    private \SplObjectStorage $updatedResources;
    private array $pageDataPropertiesChanged = [];
    private array $updatedCollectionClassToIriMapping = [];

    /**
     * @param iterable|ResourceChangedPropagatorInterface[] $resourceChangedPropagators
     */
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        ManagerRegistry $entityManager,
        private readonly iterable $resourceChangedPropagators,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
        private readonly PageDataProvider $pageDataProvider,
        private readonly ComponentPositionRepository $positionRepository
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->collectionRepository = $entityManager->getRepository(Collection::class);
        $this->reset();
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->gatherResourceAndAssociated($entity, 'created', $em, $uow);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->gatherResourceAndAssociated($entity, 'updated', $em, $uow);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->gatherResourceAndAssociated($entity, 'deleted', $em, $uow);
        }

        $this->collectUpdatedPageDataAndPositions();
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        $this->refreshUpdatedEntities($eventArgs->getObjectManager());
        $this->collectDynamicComponentPositionResources();
        $this->collectRelatedCollectionComponentResources();
        $this->purgeResources();
    }

    private function collectUpdatedPageDataAndPositions(): void
    {
        foreach ($this->updatedResources as $updatedResource) {
            $pageDataComponentMetadata = $this->pageDataProvider->findPageDataComponentMetadata($updatedResource);
            foreach ($pageDataComponentMetadata as $pageDataComponentMetadatum) {
                $pageDataResources = $pageDataComponentMetadatum->getPageDataResources();
                if (\count($pageDataResources)) {
                    foreach ($pageDataResources as $pageDataResource) {
                        $this->collectUpdatedResource($pageDataResource, 'updated');
                        $this->addToPropagators($pageDataResource, 'updated');
                    }

                    $pageDataComponentProperties = $pageDataComponentMetadatum->getProperties();
                    $this->collectDynamicComponentPositionResources($pageDataComponentProperties->toArray());
                }
            }
        }
    }

    private function refreshUpdatedEntities(ObjectManager $om): void
    {
        foreach ($this->updatedResources as $updatedResource) {
            $data = $this->updatedResources[$updatedResource];
            if ('deleted' !== $data['type'] && $om->contains($updatedResource)) {
                $om->refresh($updatedResource);
                $this->addToPropagators($updatedResource, $data['type']);
            }
        }
    }

    private function gatherResourceAndAssociated(object $entity, string $type, ObjectManager $em, UnitOfWork $uow): void
    {
        $changeSet = $uow->getEntityChangeSet($entity);
        $this->collectUpdatedResource($entity, $type);

        $associationMappings = $em->getClassMetadata(ClassUtils::getClass($entity))->getAssociationMappings();

        if ($entity instanceof PageDataInterface) {
            $this->pageDataPropertiesChanged = array_keys($changeSet);
        }

        if ('updated' === $type) {
            $this->gatherUpdatedAssociatedEntities($associationMappings, $changeSet);

            return;
        }

        // created and deleted - full entity change, all properties to check
        // catch any related resources that may have changed backwards relation or database cascades
        $this->gatherAllAssociatedEntities($entity, $associationMappings);
    }

    private function gatherUpdatedAssociatedEntities(array $associationMappings, array $changeSet): void
    {
        foreach ($changeSet as $field => $values) {
            // detect whether changed field was an association
            if (!isset($associationMappings[$field])) {
                continue;
            }

            if (isset($associationMappings[$field]['inversedBy'])) {
                $notNullValues = array_filter($values);
                foreach ($notNullValues as $entityInverseValuesUpdated) {
                    // note: the resource may get removed if orphaned
                    $this->collectUpdatedResource($entityInverseValuesUpdated, 'updated');
                }
            }
        }
    }

    private function gatherAllAssociatedEntities(object $entity, array $associationMappings): void
    {
        foreach (array_keys($associationMappings) as $property) {
            if (
                !$this->propertyAccessor->isReadable($entity, $property)
                || !$assocEntity = $this->propertyAccessor->getValue($entity, $property)
            ) {
                continue;
            }

            if ($assocEntity instanceof PersistentCollection) {
                foreach ($assocEntity as $oneToManyEntity) {
                    if (!$oneToManyEntity) {
                        continue;
                    }
                    $this->collectUpdatedResource($oneToManyEntity, 'updated');
                }
                continue;
            }
            $this->collectUpdatedResource($assocEntity, 'updated');
        }
    }

    private function collectUpdatedResource($resource, string $type): void
    {
        if (!$resource) {
            return;
        }
        $this->addResourceIrisFromObject($resource, $type);
        $this->addToPropagators($resource, $type);
    }

    private function addResourceIrisFromObject($resource, string $type): void
    {
        if (
            isset($this->updatedResources[$resource])
            && 'deleted' !== $type
        ) {
            return;
        }

        try {
            $resourceClass = $this->resourceClassResolver->getResourceClass($resource);
        } catch (InvalidArgumentException $e) {
            return;
        }

        // collect get collection iris for clearing the collection components in the cache later
        if (!isset($this->updatedCollectionClassToIriMapping[$resourceClass])) {
            try {
                $collectionIri = $this->iriConverter->getIriFromResource($resource, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
                $this->updatedCollectionClassToIriMapping[$resourceClass] = $collectionIri;
            } catch (InvalidArgumentException $e) {
            }
        }

        // keep a record of all the resources we are triggering updates for related from the database changes
        $resourceIri = $this->iriConverter->getIriFromResource($resource);

        $this->updatedResources[$resource] = [
            'iri' => $resourceIri,
            'type' => $type,
            'resourceClass' => $resourceClass,
        ];
    }

    private function collectDynamicComponentPositionResources(array $pageDataPropertiesChanged = null): void
    {
        if (!$pageDataPropertiesChanged) {
            $pageDataPropertiesChanged = $this->pageDataPropertiesChanged;
        }
        if (0 === \count($pageDataPropertiesChanged)) {
            return;
        }
        $positions = $this->positionRepository->findByPageDataProperties($pageDataPropertiesChanged);

        foreach ($positions as $position) {
            $this->collectUpdatedResource($position, 'updated');
            $this->addToPropagators($position, 'updated');
        }
    }

    private function collectRelatedCollectionComponentResources(): void
    {
        if (empty($this->updatedCollectionClassToIriMapping)) {
            return;
        }

        foreach ($this->updatedCollectionClassToIriMapping as $resourceIri) {
            $collections = $this->collectionRepository->findBy([
                'resourceIri' => $resourceIri,
            ]);
            foreach ($collections as $collection) {
                $this->collectUpdatedResource($collection, 'updated');
                $this->addToPropagators($collection, 'updated');
            }
        }
    }

    private function purgeResources(): void
    {
        foreach ($this->resourceChangedPropagators as $resourceChangedPropagator) {
            $resourceChangedPropagator->propagate();
        }

        $this->reset();
    }

    private function addToPropagators(object $item, string $type): void
    {
        foreach ($this->resourceChangedPropagators as $resourceChangedPropagator) {
            $resourceChangedPropagator->add($item, $type);
        }
    }

    private function reset(): void
    {
        $this->updatedResources = new \SplObjectStorage();
        $this->pageDataPropertiesChanged = [];
        $this->updatedCollectionClassToIriMapping = [];
    }
}
