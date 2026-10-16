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
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetLoader;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FixtureAssetLoaderTest extends TestCase
{
    private string $directory;
    private string $manifestPath;
    private string $hash;
    private Filesystem $bucket;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cwa-fixture-asset-loader-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/assets', 0777, true);
        $this->manifestPath = FixtureAssetManifest::pathFor($this->directory, 'SiteScaffold');
        $this->hash = hash('sha256', 'hello');
        $manifest = new FixtureAssetManifest();
        $manifest->add($this->hash, 'greeting.txt', 'text/plain', 5, 'App\\Entity\\Doc::file');
        file_put_contents($this->manifestPath, $manifest->toJson());
        file_put_contents($this->directory . '/assets/' . $this->hash, 'hello');
        $this->bucket = new Filesystem(new InMemoryFilesystemAdapter());
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_an_asset_is_loaded_as_an_upload_under_its_manifest_name_from_a_copy_of_the_stored_file(): void
    {
        $loader = $this->loader();
        $file = $loader->load($this->manifestPath, $this->hash);

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('greeting.txt', $file->getClientOriginalName());
        self::assertSame('text/plain', $file->getClientMimeType());
        self::assertSame('hello', file_get_contents($file->getRealPath()));
        self::assertNotSame(realpath($this->directory . '/assets/' . $this->hash), $file->getRealPath());
        self::assertTrue($file->isValid());
    }

    public function test_an_asset_can_be_loaded_under_another_name(): void
    {
        self::assertSame('copy.txt', $this->loader()->load($this->manifestPath, $this->hash, 'copy.txt')->getClientOriginalName());
    }

    public function test_the_manifest_is_read_once_per_load(): void
    {
        $loader = $this->loader();
        $loader->load($this->manifestPath, $this->hash);
        unlink($this->manifestPath);

        self::assertSame('hello', file_get_contents($loader->load($this->manifestPath, $this->hash)->getRealPath()));
    }

    public function test_reset_deletes_the_copies_and_forgets_the_manifest(): void
    {
        $loader = $this->loader();
        $copy = $loader->load($this->manifestPath, $this->hash)->getRealPath();
        self::assertFileExists($copy);

        $loader->reset();

        self::assertFileDoesNotExist($copy);
        unlink($this->manifestPath);
        $this->expectExceptionMessage('does not exist');
        $loader->load($this->manifestPath, $this->hash);
    }

    public function test_an_asset_comes_from_the_configured_flysystem_store(): void
    {
        $this->bucket->write('fixtures/' . $this->hash, 'hello');
        unlink($this->directory . '/assets/' . $this->hash);
        $loader = new FixtureAssetLoader(new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator(['gcloud' => fn () => $this->bucket])), true, 'flysystem', null, 'gcloud', 'fixtures'));

        self::assertSame('hello', file_get_contents($loader->load($this->manifestPath, $this->hash)->getRealPath()));
    }

    public function test_a_hash_the_manifest_does_not_list_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('The fixture asset %s is not in the manifest %s. Generate the scaffold and its manifest again.', hash('sha256', 'other'), $this->manifestPath));

        $this->loader()->load($this->manifestPath, hash('sha256', 'other'));
    }

    public function test_an_asset_missing_from_the_store_is_refused_naming_the_store(): void
    {
        unlink($this->directory . '/assets/' . $this->hash);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('The fixture asset %s (greeting.txt) could not be read from the fixture asset store %s: ', $this->hash, $this->directory . '/assets'));

        $this->loader()->load($this->manifestPath, $this->hash);
    }

    public function test_an_asset_whose_content_does_not_match_its_hash_is_refused(): void
    {
        file_put_contents($this->directory . '/assets/' . $this->hash, 'changed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('The fixture asset %s (greeting.txt) in the fixture asset store %s does not match its hash.', $this->hash, $this->directory . '/assets'));

        $this->loader()->load($this->manifestPath, $this->hash);
    }

    private function loader(): FixtureAssetLoader
    {
        return new FixtureAssetLoader(new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator([]))));
    }
}
