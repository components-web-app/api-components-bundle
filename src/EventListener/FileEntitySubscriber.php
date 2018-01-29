<?php

namespace Silverback\ApiComponentBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;

class FileEntitySubscriber implements EventSubscriber
{
    private $imagineCacheManager;

    /**
     * FileListener constructor.
     * @param CacheManager $imagineCacheManager
     */
    public function __construct(
        CacheManager $imagineCacheManager
    )
    {
        $this->imagineCacheManager = $imagineCacheManager;
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
    }
}
