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
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Model\Binary;
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

    public function test_a_filter_with_an_output_format_stores_its_variant_with_that_formats_extension(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $resolver = $this->createResolver($filesystem);

        $resolver->store(new Binary('webp bytes', 'image/webp', 'webp'), 'photo-1a2b3c4d.png', 'webp_thumbnail');

        self::assertTrue($filesystem->fileExists('media/cache/webp_thumbnail/photo-1a2b3c4d.webp'));
        self::assertFalse($filesystem->fileExists('media/cache/webp_thumbnail/photo-1a2b3c4d.png'));
        self::assertTrue($resolver->isStored('photo-1a2b3c4d.png', 'webp_thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/webp_thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.png', 'webp_thumbnail'));
    }

    public function test_removing_a_variant_with_an_output_format_deletes_the_file_with_that_formats_extension(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $resolver = $this->createResolver($filesystem);
        $resolver->store(new Binary('webp bytes', 'image/webp', 'webp'), 'photo-1a2b3c4d.png', 'webp_thumbnail');

        $resolver->remove(['photo-1a2b3c4d.png'], ['webp_thumbnail']);

        self::assertFalse($filesystem->fileExists('media/cache/webp_thumbnail/photo-1a2b3c4d.webp'));
        self::assertFalse($resolver->isStored('photo-1a2b3c4d.png', 'webp_thumbnail'));
    }

    public function test_a_filter_with_no_output_format_keeps_the_source_extension(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $resolver = $this->createResolver($filesystem);

        $resolver->store(new Binary('png bytes', 'image/png', 'png'), 'photo-1a2b3c4d.png', 'thumbnail');

        self::assertTrue($filesystem->fileExists('media/cache/thumbnail/photo-1a2b3c4d.png'));
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.png', $resolver->resolve('photo-1a2b3c4d.png', 'thumbnail'));
    }

    public function test_a_source_extension_valid_for_the_output_format_is_kept(): void
    {
        $resolver = $this->createResolver(new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertSame('https://cdn.example.com/media/cache/jpeg_thumbnail/photo-1a2b3c4d.jpeg', $resolver->resolve('photo-1a2b3c4d.jpeg', 'jpeg_thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/jpeg_thumbnail/photo-1a2b3c4d.jpg', $resolver->resolve('photo-1a2b3c4d.png', 'jpeg_thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/webp_thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.WEBP', 'webp_thumbnail'));
    }

    public function test_a_source_with_no_extension_gets_the_output_formats_extension(): void
    {
        $resolver = $this->createResolver(new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertSame('https://cdn.example.com/media/cache/webp_thumbnail/uploads/scan.webp', $resolver->resolve('uploads/scan', 'webp_thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/webp_thumbnail/uploads.v2/scan.webp', $resolver->resolve('uploads.v2/scan', 'webp_thumbnail'));
    }

    public function test_a_filter_unknown_to_the_filter_configuration_keeps_the_source_extension(): void
    {
        $resolver = $this->createResolver(new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertSame('https://cdn.example.com/media/cache/runtime/photo-1a2b3c4d.png', $resolver->resolve('photo-1a2b3c4d.png', 'runtime'));
    }

    private function createResolver(Filesystem $filesystem): FlysystemCacheResolver
    {
        return new FlysystemCacheResolver($filesystem, 'https://cdn.example.com', 'media/cache', filterConfiguration: new FilterConfiguration([
            'thumbnail' => ['filters' => []],
            'webp_thumbnail' => ['format' => 'webp', 'filters' => []],
            'jpeg_thumbnail' => ['format' => 'jpeg', 'filters' => []],
        ]));
    }
}
