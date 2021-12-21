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

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use Psr\Cache\CacheItemPoolInterface;
use Silverback\ApiComponentsBundle\Cache\CachedTrait;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 * @description Based on API Platform CachedResourceMetadataFactory by Teoh Han Hui <teohhanhui@gmail.com>
 */
class CachedPageDataMetadataFactory implements PageDataMetadataFactoryInterface
{
    use CachedTrait;

    public const CACHE_KEY_PREFIX = 'page_data_metadata_';

    private PageDataMetadataFactoryInterface $decorated;

    public function __construct(CacheItemPoolInterface $cacheItemPool, PageDataMetadataFactoryInterface $decorated)
    {
        $this->cacheItemPool = $cacheItemPool;
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $resourceClass): PageDataMetadata
    {
        $cacheKey = self::CACHE_KEY_PREFIX . md5($resourceClass);

        return $this->getCached($cacheKey, function () use ($resourceClass) {
            return $this->decorated->create($resourceClass);
        });
    }
}
