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

use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;

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
    private FileInfoCacheManager $fileInfoCacheManager;

    public function __construct(FileInfoCacheManager $fileInfoCacheManager)
    {
        $this->fileInfoCacheManager = $fileInfoCacheManager;
    }

    public function onStore(ImagineStoreEvent $event): void
    {
        $content = $event->binary->getContent();
        [ $width, $height ] = getimagesizefromstring($content);
        $fileSize = \strlen($content);

        $fileInfo = new FileInfo($event->path, $event->binary->getMimeType(), $fileSize, $width, $height, $event->filter);
        $this->fileInfoCacheManager->saveCache($fileInfo);
    }

    public function onRemove(ImagineRemoveEvent $event): void
    {
        $this->fileInfoCacheManager->deleteCaches($event->paths, $event->filters);
    }
}
