<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Validator\ImagineSupportedFilePath;

class FileInterfaceSubscriber implements EntitySubscriberInterface
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
     * @var PathResolver
     */
    private $pathResolver;

    /**
     * @param CacheManager $imagineCacheManager
     * @param FilterService $filterService
     * @param PathResolver $pathResolver
     */
    public function __construct(
        CacheManager $imagineCacheManager,
        FilterService $filterService,
        PathResolver $pathResolver
    ) {
        $this->imagineCacheManager = $imagineCacheManager;
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

    public function supportsEntity($entity = null): bool
    {
        return true;
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     */
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getEntityManager();
        if ($entityManager->getConnection()->getDatabasePlatform() instanceof SqlitePlatform) {
            $entityManager->getConnection()->exec('PRAGMA foreign_keys = ON;');
        }
        $unitOfWork = $entityManager->getUnitOfWork();
        $this->processNewEntities($unitOfWork, $entityManager);
        $this->processUpdatedEntities($unitOfWork);
        $this->processDeletedEntities($unitOfWork);
    }

    /**
     * @param UnitOfWork $unitOfWork
     * @param EntityManagerInterface $entityManager
     */
    private function processNewEntities(UnitOfWork $unitOfWork, EntityManagerInterface $entityManager): void
    {
        $newEntities = $unitOfWork->getScheduledEntityInsertions();
        foreach ($newEntities as $entity) {
            // This should not really be used here, it won't be fired as validation would fail first
            // Dynamic pages sort values need work with auto-calculating
            if ($entity instanceof SortableInterface && $entity->getSort() === null) {
                $entity->setSort($entity->calculateSort(true));
                $metadata = $entityManager->getClassMetadata(\get_class($entity));
                $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
            }
            if (
                $entity instanceof FileInterface &&
                ImagineSupportedFilePath::isValidFilePath($entity->getFilePath())
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
                        if (ImagineSupportedFilePath::isValidFilePath($previousValueForField)) {
                            $this->imagineCacheManager->remove($previousValueForField);
                        }
                        if (ImagineSupportedFilePath::isValidFilePath($newValueForField)) {
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
                ImagineSupportedFilePath::isValidFilePath($entity->getFilePath())
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
