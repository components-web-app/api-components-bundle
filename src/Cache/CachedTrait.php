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

namespace Silverback\ApiComponentsBundle\Cache;

use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @description Copied from API Platform implementation - no author specified in file
 *
 * @internal
 */
trait CachedTrait
{
    private CacheItemPoolInterface $cacheItemPool;
    private array $localCache = [];

    private function getCached(string $cacheKey, callable $getValue)
    {
        if (\array_key_exists($cacheKey, $this->localCache)) {
            return $this->localCache[$cacheKey];
        }

        try {
            $cacheItem = $this->cacheItemPool->getItem($cacheKey);
        } catch (CacheException $e) {
            return $this->localCache[$cacheKey] = $getValue();
        }

        if ($cacheItem->isHit()) {
            return $this->localCache[$cacheKey] = $cacheItem->get();
        }

        $value = $getValue();

        $cacheItem->set($value);
        $this->cacheItemPool->save($cacheItem);

        return $this->localCache[$cacheKey] = $value;
    }
}
