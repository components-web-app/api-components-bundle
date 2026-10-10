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
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Cache\SignerInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

class CacheManagerTest extends TestCase
{
    public function test_a_stored_variant_is_read_through_the_filters_flysystem_resolver(): void
    {
        $cache = new Filesystem(new InMemoryFilesystemAdapter());
        $cache->write('media/cache/thumbnail/photo.png', 'variant');
        $cacheManager = $this->createCacheManager();
        $cacheManager->addResolver('default', new FlysystemCacheResolver($cache, '/'));

        self::assertSame('variant', $cacheManager->readStored('photo.png', 'thumbnail'));
        self::assertNull($cacheManager->readStored('other.png', 'thumbnail'));
    }

    public function test_a_variant_behind_any_other_resolver_cannot_be_read(): void
    {
        $resolver = $this->createMock(ResolverInterface::class);
        $resolver->expects(self::never())->method(self::anything());
        $cacheManager = $this->createCacheManager();
        $cacheManager->addResolver('default', $resolver);

        self::assertNull($cacheManager->readStored('photo.png', 'thumbnail'));
    }

    private function createCacheManager(): CacheManager
    {
        return new CacheManager(
            new FilterConfiguration(['thumbnail' => ['filters' => []]]),
            $this->createStub(RouterInterface::class),
            $this->createStub(SignerInterface::class),
            new EventDispatcher(),
        );
    }
}
