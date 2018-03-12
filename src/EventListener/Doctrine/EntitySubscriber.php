<?php

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;

/**
 * Class FileEntitySubscriber
 * @package Silverback\ApiComponentBundle\EventListener
 * @author Daniel West <daniel@silverback.is>
 */
class EntitySubscriber implements EventSubscriber
{
    /**
     * @var CacheManager
     */
    private $imagineCacheManager;
    /**
     * @var FilterService
     */
    private $filterService;
    /**
     * @var ApiNormalizer
     */
    private $fileNormalizer;
    /**
     * @var PathResolver
     */
    private $pathResolver;

    /**
     * FileListener constructor.
     * @param CacheManager $imagineCacheManager
     * @param FilterService $filterService
     * @param ApiNormalizer $fileNormalizer
     * @param PathResolver $pathResolver
     */
    public function __construct(
        CacheManager $imagineCacheManager,
        FilterService $filterService,
        ApiNormalizer $fileNormalizer,
        PathResolver $pathResolver
    ) {
        $this->imagineCacheManager = $imagineCacheManager;
        $this->fileNormalizer = $fileNormalizer;
        $this->filterService = $filterService;
        $this->pathResolver = $pathResolver;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            'onFlush'
        ];
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     * @throws \Doctrine\DBAL\DBALException
     */
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getEntityManager();
        if ($entityManager->getConnection()->getDatabasePlatform() instanceof SqlitePlatform) {
            $entityManager->getConnection()->exec('PRAGMA foreign_keys = ON;');
        }
        $unitOfWork = $entityManager->getUnitOfWork();
        $this->processNewEntities($unitOfWork);
        $this->processUpdatedEntities($unitOfWork);
        $this->processDeletedEntities($unitOfWork);
    }

    /**
     * @param UnitOfWork $unitOfWork
     */
    private function processNewEntities(UnitOfWork $unitOfWork): void
    {
        $newEntities = $unitOfWork->getScheduledEntityInsertions();
        foreach ($newEntities as $entity) {
            if ($entity instanceof SortableInterface) {
                try {
                    $entity->getSort();
                } catch (\TypeError $e) {
                    $entity->setSort($entity->calculateSort(true));
                }
            }
            if (
                $entity instanceof FileInterface &&
                $this->fileNormalizer->isImagineSupportedFile($entity->getFilePath())
            ) {
                $this->createFilteredImages($entity);
            }
        }
    }

    /**
     * @param UnitOfWork $unitOfWork
     */
    private function processUpdatedEntities(UnitOfWork $unitOfWork): void
    {
        $updatedEntities = $unitOfWork->getScheduledEntityUpdates();
        foreach ($updatedEntities as $entity) {
            if ($entity instanceof FileInterface) {
                $changes = $unitOfWork->getEntityChangeSet($entity);
                if (!\is_array($changes)) {
                    return;
                }
                if (array_key_exists('filePath', $changes)) {
                    $fpChanges = $changes['filePath'];
                    $previousValueForField = $fpChanges[0] ?? null;
                    $newValueForField = $fpChanges[1] ?? null;
                    if ($previousValueForField !== $newValueForField) {
                        if ($this->fileNormalizer->isImagineSupportedFile($previousValueForField)) {
                            $this->imagineCacheManager->remove($previousValueForField);
                        }
                        if ($this->fileNormalizer->isImagineSupportedFile($newValueForField)) {
                            $this->createFilteredImages($entity);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param UnitOfWork $unitOfWork
     */
    private function processDeletedEntities(UnitOfWork $unitOfWork): void
    {
        $deletedEntities = $unitOfWork->getScheduledEntityDeletions();
        foreach ($deletedEntities as $entity) {
            if (
                $entity instanceof FileInterface &&
                $this->fileNormalizer->isImagineSupportedFile($entity->getFilePath())
            ) {
                $this->imagineCacheManager->remove($entity->getFilePath());
            }
        }
    }

    private function createFilteredImages(FileInterface $file): void
    {
        $filters = $file::getImagineFilters();
        foreach ($filters as $filter) {
            $this->filterService->getUrlOfFilteredImage($this->pathResolver->resolve($file->getFilePath()), $filter);
        }
    }
}
