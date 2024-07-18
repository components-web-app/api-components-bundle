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

namespace Silverback\ApiComponentsBundle\Imagine;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager as ImagineCacheManager;
use Silverback\ApiComponentsBundle\Event\ImagineRemoveEvent;
use Silverback\ApiComponentsBundle\Event\ImagineStoreEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class CacheManager extends ImagineCacheManager
{
    public function store(BinaryInterface $binary, $path, $filter, $resolver = null): void
    {
        parent::store($binary, $path, $filter, $resolver);
        $event = new ImagineStoreEvent($binary, $path, $filter);
        $this->dispatch($event, ImagineStoreEvent::class);
    }

    public function remove($paths = null, $filters = null): void
    {
        parent::remove($paths, $filters);
        $event = new ImagineRemoveEvent($paths, $filters);
        $this->dispatch($event, ImagineRemoveEvent::class);
    }

    private function dispatch($event, $eventName): void
    {
        $this->dispatcher->dispatch($event, $eventName);
    }
}
