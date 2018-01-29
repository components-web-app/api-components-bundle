<?php

namespace Silverback\ApiComponentBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Enqueue\Client\Producer;
use Liip\ImagineBundle\Async\Commands;
use Liip\ImagineBundle\Async\ResolveCache;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;

class FileEntitySubscriber implements EventSubscriber
{
    private $imagineCacheManager;
    private $producer;

    /**
     * FileListener constructor.
     * @param CacheManager $imagineCacheManager
     * @param Producer $producer
     */
    public function __construct(
        CacheManager $imagineCacheManager,
        Producer $producer
    )
    {
        $this->imagineCacheManager = $imagineCacheManager;
        $this->producer = $producer;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'onFlush'
        );
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     * @throws \Enqueue\Rpc\TimeoutException
     */
    public function onFlush (OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getEntityManager();
        $unitOfWork = $entityManager->getUnitOfWork();
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
                    if ($previousValueForField && $previousValueForField !== $newValueForField) {
                        $this->imagineCacheManager->remove($previousValueForField);
                        $promise = $this->producer->sendCommand(Commands::RESOLVE_CACHE, new ResolveCache($newValueForField), true);
                        $promise->receive(20000);
                    }
                }
            }
        }

        $deletedEntities = $unitOfWork->getScheduledEntityDeletions();
        foreach ($deletedEntities as $entity) {
            if ($entity instanceof FileInterface) {
                $this->imagineCacheManager->remove($entity->getFilePath());
            }
        }

        $newEntities = $unitOfWork->getScheduledEntityInsertions();
        foreach ($newEntities as $entity) {
            if ($entity instanceof FileInterface) {
                $promise = $this->producer->sendCommand(Commands::RESOLVE_CACHE, new ResolveCache($entity->getFilePath()), true);
                $promise->receive(20000);
            }
        }
    }
}
