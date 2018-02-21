<?php

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Enqueue\Client\Producer;
use Liip\ImagineBundle\Async\Commands;
use Liip\ImagineBundle\Async\ResolveCache;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
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
     * @var Producer
     */
    private $producer;
    /**
     * @var ApiNormalizer
     */
    private $fileNormalizer;

    /**
     * FileListener constructor.
     * @param CacheManager $imagineCacheManager
     * @param Producer $producer
     * @param ApiNormalizer $fileNormalizer
     */
    public function __construct(
        CacheManager $imagineCacheManager,
        Producer $producer,
        ApiNormalizer $fileNormalizer
    ) {
        $this->imagineCacheManager = $imagineCacheManager;
        $this->producer = $producer;
        $this->fileNormalizer = $fileNormalizer;
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
     * @throws \Enqueue\Rpc\TimeoutException
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
     * @throws \Enqueue\Rpc\TimeoutException
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
                $this->sendCommand($entity);
            }
        }
    }

    /**
     * @param UnitOfWork $unitOfWork
     * @throws \Enqueue\Rpc\TimeoutException
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
                            $this->sendCommand($entity);
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

    /**
     * @param FileInterface $file
     * @throws \Enqueue\Rpc\TimeoutException
     */
    private function sendCommand(FileInterface $file): void
    {
        $this->producer
            ->sendCommand(
                Commands::RESOLVE_CACHE,
                new ResolveCache($file->getFilePath(), $file::getImagineFilters()),
                true
            )
            ->receive(20000)
        ;
    }
}
