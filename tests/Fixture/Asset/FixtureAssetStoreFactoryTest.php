<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Asset;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStore;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

class FixtureAssetStoreFactoryTest extends TestCase
{
    private const string A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private Filesystem $bucket;
    private string $directory;

    protected function setUp(): void
    {
        $this->bucket = new Filesystem(new InMemoryFilesystemAdapter());
        $this->directory = sys_get_temp_dir() . '/cwa-fixture-asset-store-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_with_no_configuration_and_no_override_generation_keeps_readable_files_beside_the_scaffold(): void
    {
        self::assertNull($this->factory()->forGeneration($this->directory));
    }

    public function test_with_no_configuration_a_scaffold_loads_its_assets_from_the_folder_beside_it(): void
    {
        $store = $this->factory()->forLoading($this->directory);
        $store->write(self::A, $this->stream('a'));

        self::assertTrue($store->isLocal());
        self::assertSame($this->directory . '/assets', $store->description);
        self::assertFileExists($this->directory . '/assets/' . self::A);
    }

    public function test_a_configured_local_store_uses_its_path_for_both_generating_and_loading(): void
    {
        $factory = $this->factory(enabled: true, path: $this->directory . '/shared');

        foreach ([$factory->forGeneration('/elsewhere'), $factory->forLoading('/elsewhere')] as $store) {
            self::assertInstanceOf(FixtureAssetStore::class, $store);
            self::assertTrue($store->isLocal());
            self::assertSame($this->directory . '/shared', $store->description);
        }
    }

    public function test_a_local_store_creates_no_folder_until_it_writes(): void
    {
        $store = $this->factory(enabled: true, path: $this->directory . '/lazy')->forGeneration('/elsewhere');

        self::assertSame([], $store?->hashes());
        self::assertDirectoryDoesNotExist($this->directory . '/lazy');
    }

    public function test_a_configured_local_store_with_no_path_is_the_folder_beside_the_scaffold(): void
    {
        self::assertSame($this->directory . '/assets', $this->factory(enabled: true)->forGeneration($this->directory)?->description);
    }

    public function test_a_flysystem_store_uses_the_adapter_and_prefix_for_both_generating_and_loading(): void
    {
        $factory = $this->factory(enabled: true, store: 'flysystem', adapter: 'gcloud', prefix: '/fixtures/site/');

        foreach ([$factory->forGeneration($this->directory), $factory->forLoading($this->directory)] as $store) {
            self::assertInstanceOf(FixtureAssetStore::class, $store);
            self::assertFalse($store->isLocal());
            self::assertSame('the gcloud filesystem under fixtures/site', $store->description);
        }
        $factory->forLoading($this->directory)->write(self::A, $this->stream('a'));
        self::assertSame('a', $this->bucket->read('fixtures/site/' . self::A));
    }

    public function test_a_flysystem_store_with_no_prefix_says_so(): void
    {
        self::assertSame('the gcloud filesystem', $this->factory(enabled: true, store: 'flysystem', adapter: 'gcloud')->forLoading($this->directory)->description);
    }

    public function test_a_store_given_for_one_run_is_used_without_configuration(): void
    {
        self::assertSame($this->directory . '/assets', $this->factory()->forGeneration($this->directory, 'local')?->description);
        self::assertSame('the gcloud filesystem', $this->factory(adapter: 'gcloud')->forGeneration($this->directory, 'flysystem')?->description);
    }

    public function test_a_directory_given_for_one_run_is_a_local_store_whatever_is_configured(): void
    {
        $factory = $this->factory(enabled: true, store: 'flysystem', adapter: 'gcloud');

        $store = $factory->forGeneration('/elsewhere', null, $this->directory . '/one-off');

        self::assertTrue($store?->isLocal());
        self::assertSame($this->directory . '/one-off', $store->description);
        self::assertSame($this->directory . '/one-off', $this->factory()->forGeneration('/elsewhere', 'local', $this->directory . '/one-off')?->description);
        self::assertSame($this->directory . '/one-off', $this->factory(enabled: true, path: '/configured')->forGeneration('/elsewhere', null, $this->directory . '/one-off')?->description);
    }

    public static function invalidStores(): iterable
    {
        yield 'an unknown store' => [['enabled' => true, 'store' => 's3'], null, null, 'The fixture asset store "s3" is not one of "local" or "flysystem".'];
        yield 'an unknown store for one run' => [[], 's3', null, 'The fixture asset store "s3" is not one of "local" or "flysystem".'];
        yield 'a flysystem store with no adapter' => [['enabled' => true, 'store' => 'flysystem'], null, null, 'The flysystem fixture asset store needs an adapter.'];
        yield 'an empty adapter' => [['enabled' => true, 'store' => 'flysystem', 'adapter' => ''], null, null, 'The flysystem fixture asset store needs an adapter.'];
        yield 'an adapter that does not exist' => [['enabled' => true, 'store' => 'flysystem', 'adapter' => 'missing'], null, null, 'The fixture asset store adapter "missing" is not a filesystem adapter of this application.'];
        yield 'a directory for a flysystem store' => [['adapter' => 'gcloud'], 'flysystem', '/tmp/assets', 'A directory can only be given for the local fixture asset store.'];
    }

    #[DataProvider('invalidStores')]
    public function test_an_invalid_store_is_refused_with_the_reason(array $config, ?string $store, ?string $directory, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->factory(...$config)->forGeneration($this->directory, $store, $directory);
    }

    public function test_an_invalid_configured_store_is_refused_when_loading_too(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The flysystem fixture asset store needs an adapter.');

        $this->factory(enabled: true, store: 'flysystem')->forLoading($this->directory);
    }

    private function factory(bool $enabled = false, string $store = 'local', ?string $path = null, ?string $adapter = null, string $prefix = ''): FixtureAssetStoreFactory
    {
        return new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator(['gcloud' => fn () => $this->bucket])), $enabled, $store, $path, $adapter, $prefix);
    }

    /**
     * @return resource
     */
    private function stream(string $contents)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
