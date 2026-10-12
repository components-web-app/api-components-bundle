<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Imagine;

use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;

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
        [$width, $height] = getimagesizefromstring($content);
        $fileSize = \strlen($content);

        $fileInfo = new FileInfo($event->path, $event->binary->getMimeType(), $fileSize, $width, $height, $event->filter);
        $this->fileInfoCacheManager->deleteCaches([$event->path], [$event->filter]);
        $this->fileInfoCacheManager->saveCache($fileInfo);
    }

    public function onRemove(ImagineRemoveEvent $event): void
    {
        $this->fileInfoCacheManager->deleteCaches($event->paths, $event->filters);
    }
}
