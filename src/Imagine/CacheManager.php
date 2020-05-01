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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class CacheManager extends ImagineCacheManager
{
    public function store(BinaryInterface $binary, $path, $filter, $resolver = null): void
    {
        $event = new ImagineStoreEvent();
        $event->binary = $binary;
        $this->dispatch($event, ImagineStoreEvent::class);
        parent::store($binary, $path, $filter, $resolver);
    }

    public function remove($paths = null, $filters = null): void
    {
        $event = new ImagineRemoveEvent();
        $event->paths = $paths;
        $event->filters = $filters;
        $this->dispatch($event, ImagineRemoveEvent::class);
        parent::remove($paths, $filters);
    }

    private function dispatch($event, $eventName): void
    {
        if ($this->dispatcher instanceof ContractsEventDispatcherInterface) {
            $this->dispatcher->dispatch($event, $eventName);
        } else {
            $this->dispatcher->dispatch($eventName, $event);
        }
    }
}
