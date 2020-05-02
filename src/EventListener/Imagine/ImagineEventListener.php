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

namespace Silverback\ApiComponentsBundle\EventListener\Imagine;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Imagine\Entity\ImagineCachedFileMetadata;

/**
 * This will listen to cache events in imagine bundle and persist the metadata to the database
 * ISSUE! We may be in the middle of other transactions too as the main part of our REST API.
 * We should modify this to isolate this to only persist/flush changes made here.
 *
 * Possibly duplicate the entity manager? If we can just create a new Unit Of Work?
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineEventListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $entityManager->getUnitOfWork();
    }

    public function onStore(ImagineStoreEvent $event): void
    {
        $content = $event->binary->getContent();
        [ $width, $height ] = getimagesizefromstring($content);
        $filesize = \strlen($content);

        $metadata = new ImagineCachedFileMetadata($event->filter, $event->path, $event->binary->getMimeType(), $width, $height, $filesize);
        $this->entityManager->persist($metadata);
        $this->entityManager->flush();
    }

    public function onRemove(ImagineRemoveEvent $event): void
    {
        $repository = $this->entityManager->getRepository(ImagineCachedFileMetadata::class);
        foreach ($event->filters as $filter) {
            foreach ($event->paths as $path) {
                $metadata = $repository->findOneBy([
                    'filter' => $filter,
                    'path' => $path,
                ]);
                if ($metadata) {
                    $this->entityManager->remove($metadata);
                }
            }
        }
        $this->entityManager->flush();
    }
}
