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

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Tests\Flysystem\RecordingFilesystemAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;

require_once __DIR__ . '/../Flysystem/cloud_adapter_stubs.php';

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

    public function test_a_variant_of_an_avif_or_heic_source_is_stored_with_the_webp_extension(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $resolver = $this->createResolver($filesystem);

        $resolver->store(new Binary('webp bytes', 'image/webp', 'webp'), 'photo-1a2b3c4d.avif', 'thumbnail');

        self::assertTrue($filesystem->fileExists('media/cache/thumbnail/photo-1a2b3c4d.webp'));
        self::assertTrue($resolver->isStored('photo-1a2b3c4d.avif', 'thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.avif', 'thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.heic', 'thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.HEIF', 'thumbnail'));

        $resolver->remove(['photo-1a2b3c4d.avif'], ['thumbnail']);

        self::assertFalse($filesystem->fileExists('media/cache/thumbnail/photo-1a2b3c4d.webp'));
    }

    public function test_a_configured_format_wins_over_webp_for_an_avif_source(): void
    {
        $resolver = $this->createResolver(new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertSame('https://cdn.example.com/media/cache/jpeg_thumbnail/photo-1a2b3c4d.jpg', $resolver->resolve('photo-1a2b3c4d.avif', 'jpeg_thumbnail'));
    }

    public function test_an_avif_source_gets_the_webp_extension_without_a_filter_configuration(): void
    {
        $resolver = new FlysystemCacheResolver(new Filesystem(new InMemoryFilesystemAdapter()), 'https://cdn.example.com');

        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.webp', $resolver->resolve('photo-1a2b3c4d.avif', 'thumbnail'));
        self::assertSame('https://cdn.example.com/media/cache/thumbnail/photo-1a2b3c4d.png', $resolver->resolve('photo-1a2b3c4d.png', 'thumbnail'));
    }

    public function test_a_variant_is_written_with_the_imagine_cache_control_in_the_form_of_its_adapter(): void
    {
        $s3 = new AwsS3V3Adapter();
        $gcs = new GoogleCloudStorageAdapter();
        $factory = new FilesystemFactory(new ServiceLocator(['s3' => static fn () => $s3, 'gcs' => static fn () => $gcs]));
        $cacheControl = new UploadableCacheControl($factory, 'public, max-age=31536000, immutable', 'public, max-age=2592000');

        $this->createResolver($factory->create('s3'), $cacheControl)->store(new Binary('webp bytes', 'image/webp', 'webp'), 'photo-1a2b3c4d.png', 'webp_thumbnail');
        $this->createResolver($factory->create('gcs'), $cacheControl)->store(new Binary('webp bytes', 'image/webp', 'webp'), 'photo-1a2b3c4d.png', 'webp_thumbnail');

        $s3Config = $s3->writes['media/cache/webp_thumbnail/photo-1a2b3c4d.webp'];
        self::assertSame('public, max-age=2592000', $s3Config->get('CacheControl'));
        self::assertSame('image/webp', $s3Config->get('mimetype'));
        self::assertSame('public', $s3Config->get('visibility'));
        self::assertSame(['cacheControl' => 'public, max-age=2592000'], $gcs->writes['media/cache/webp_thumbnail/photo-1a2b3c4d.webp']->get('metadata'));
    }

    public function test_a_variant_of_a_source_registered_for_a_field_is_written_with_that_fields_imagine_cache_control(): void
    {
        $adapter = new AwsS3V3Adapter();
        $factory = new FilesystemFactory(new ServiceLocator(['s3' => static fn () => $adapter]));
        $cacheControl = new UploadableCacheControl($factory, null, 'public, max-age=2592000');
        $cacheControl->registerVariantSource('photo-1a2b3c4d.png', new UploadableField(adapter: 's3', imagineCacheControl: 'public, max-age=600'));
        $cacheControl->registerVariantSource('logo-1a2b3c4d.png', new UploadableField(adapter: 's3', imagineCacheControl: false));
        $resolver = $this->createResolver($factory->create('s3'), $cacheControl);

        $resolver->store(new Binary('png bytes', 'image/png', 'png'), 'photo-1a2b3c4d.png', 'thumbnail');
        $resolver->store(new Binary('png bytes', 'image/png', 'png'), 'logo-1a2b3c4d.png', 'thumbnail');

        self::assertSame('public, max-age=600', $adapter->writes['media/cache/thumbnail/photo-1a2b3c4d.png']->get('CacheControl'));
        self::assertNull($adapter->writes['media/cache/thumbnail/logo-1a2b3c4d.png']->get('CacheControl'));
    }

    public function test_a_resolver_with_no_cache_control_writes_only_the_visibility_and_mime_type(): void
    {
        $adapter = new RecordingFilesystemAdapter();

        (new FlysystemCacheResolver(new Filesystem($adapter), '/', 'media/cache', 'private'))->store(new Binary('png bytes', 'image/png', 'png'), 'photo-1a2b3c4d.png', 'thumbnail');

        $config = $adapter->writes['media/cache/thumbnail/photo-1a2b3c4d.png'];
        self::assertSame('private', $config->get('visibility'));
        self::assertSame('image/png', $config->get('mimetype'));
        self::assertNull($config->get('metadata'));
        self::assertNull($config->get('CacheControl'));
    }

    private function createResolver(Filesystem $filesystem, ?UploadableCacheControl $cacheControl = null): FlysystemCacheResolver
    {
        return new FlysystemCacheResolver($filesystem, 'https://cdn.example.com', 'media/cache', cacheControl: $cacheControl, filterConfiguration: new FilterConfiguration([
            'thumbnail' => ['filters' => []],
            'webp_thumbnail' => ['format' => 'webp', 'filters' => []],
            'jpeg_thumbnail' => ['format' => 'jpeg', 'filters' => []],
        ]));
    }
}
