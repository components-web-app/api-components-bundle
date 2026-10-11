<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Utility;

use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\StoredFileWriteConfig;

require_once __DIR__ . '/../Flysystem/cloud_adapter_stubs.php';

class StoredFileWriteConfigTest extends TestCase
{
    private const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function test_google_cloud_storage_gets_cache_control_in_its_metadata_beside_the_content_type(): void
    {
        $config = StoredFileWriteConfig::withCacheControl(
            self::adapter(GoogleCloudStorageAdapter::class),
            ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png']],
            self::CACHE_CONTROL,
        );

        self::assertSame(
            ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png', 'cacheControl' => self::CACHE_CONTROL]],
            $config,
        );
    }

    public function test_google_cloud_storage_gets_a_metadata_array_when_the_write_had_none(): void
    {
        $config = StoredFileWriteConfig::withCacheControl(
            self::adapter(GoogleCloudStorageAdapter::class),
            ['visibility' => 'public', 'mimetype' => 'image/webp'],
            self::CACHE_CONTROL,
        );

        self::assertSame(
            ['visibility' => 'public', 'mimetype' => 'image/webp', 'metadata' => ['cacheControl' => self::CACHE_CONTROL]],
            $config,
        );
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function s3Adapters(): iterable
    {
        yield 'AWS SDK' => [AwsS3V3Adapter::class];
        yield 'AsyncAws' => [AsyncAwsS3Adapter::class];
    }

    #[DataProvider('s3Adapters')]
    public function test_s3_gets_the_cache_control_option(string $adapterClass): void
    {
        $config = StoredFileWriteConfig::withCacheControl(
            self::adapter($adapterClass),
            ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png']],
            self::CACHE_CONTROL,
        );

        self::assertSame(
            ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png'], 'CacheControl' => self::CACHE_CONTROL],
            $config,
        );
    }

    public function test_a_subclass_of_a_supported_adapter_gets_its_parents_form(): void
    {
        $adapter = new class extends GoogleCloudStorageAdapter {
        };

        self::assertSame(
            ['metadata' => ['cacheControl' => self::CACHE_CONTROL]],
            StoredFileWriteConfig::withCacheControl($adapter, [], self::CACHE_CONTROL),
        );
    }

    /**
     * @return iterable<string, array{?object}>
     */
    public static function otherAdapters(): iterable
    {
        yield 'in memory' => [new InMemoryFilesystemAdapter()];
        yield 'local' => [new LocalFilesystemAdapter(sys_get_temp_dir())];
        yield 'unknown' => [null];
    }

    #[DataProvider('otherAdapters')]
    public function test_any_other_adapter_is_given_the_config_unchanged(?object $adapter): void
    {
        $config = ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png']];

        self::assertSame($config, StoredFileWriteConfig::withCacheControl($adapter, $config, self::CACHE_CONTROL));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function supportedAdapters(): iterable
    {
        yield 'Google Cloud Storage' => [GoogleCloudStorageAdapter::class];
        yield from self::s3Adapters();
    }

    #[DataProvider('supportedAdapters')]
    public function test_no_cache_control_leaves_the_config_unchanged(string $adapterClass): void
    {
        $config = ['mimetype' => 'image/png', 'metadata' => ['contentType' => 'image/png']];

        self::assertSame($config, StoredFileWriteConfig::withCacheControl(self::adapter($adapterClass), $config, null));
    }

    /**
     * @param class-string $class
     */
    private static function adapter(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
