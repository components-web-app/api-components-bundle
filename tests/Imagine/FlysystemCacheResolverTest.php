<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;

class FlysystemCacheResolverTest extends TestCase
{
    public function test_the_cache_prefix_is_the_directory_variants_are_stored_under(): void
    {
        $resolver = new FlysystemCacheResolver(new Filesystem(new InMemoryFilesystemAdapter()), 'https://cdn.example.com', '/media//cache');

        self::assertSame('media/cache', $resolver->getCachePrefix());
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/image.png', $resolver->resolve('image.png', 'thumbnail'));
    }

    public function test_the_default_cache_prefix_is_media_cache(): void
    {
        self::assertSame('media/cache', (new FlysystemCacheResolver(new Filesystem(new InMemoryFilesystemAdapter()), '/'))->getCachePrefix());
    }
}
