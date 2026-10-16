<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStore;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\ContentAddressedFileExporter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ContentAddressedFileExporterTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Filesystem $uploads;
    private Filesystem $assets;
    private FixtureAssetManifest $manifest;
    private ImportCollector $imports;

    protected function setUp(): void
    {
        $this->uploads = new Filesystem(new InMemoryFilesystemAdapter());
        $this->assets = new Filesystem(new InMemoryFilesystemAdapter());
        $this->manifest = new FixtureAssetManifest();
        $this->imports = new ImportCollector('SiteScaffold');
    }

    public function test_a_file_is_stored_under_its_content_hash_and_referred_to_by_it(): void
    {
        $png = (string) base64_decode(self::PNG, true);
        $hash = hash('sha256', $png);
        $this->uploads->write('components/home-hero-0a1b2c3d.png', $png);

        $expression = $this->exporter()->expression('local', 'components/home-hero-0a1b2c3d.png', 'App\\Entity\\Hero::file', $this->imports);

        self::assertSame(\sprintf("\$cwa->asset('%s')", $hash), $expression);
        self::assertSame($png, $this->assets->read('fixtures/' . $hash));
        self::assertSame(['name' => 'home-hero.png', 'mimeType' => 'image/png', 'size' => \strlen($png), 'fields' => ['App\\Entity\\Hero::file']], $this->manifest->get($hash));
        self::assertNotContains('use Symfony\\Component\\HttpFoundation\\File\\File;', $this->imports->lines());
    }

    public function test_a_file_used_twice_is_stored_once_and_every_field_is_recorded(): void
    {
        $this->uploads->write('a/photo-0a1b2c3d.png', 'same');
        $this->uploads->write('b/photo-9f8e7d6c.png', 'same');
        $exporter = $this->exporter();

        $first = $exporter->expression('local', 'a/photo-0a1b2c3d.png', 'App\\Entity\\Hero::file', $this->imports);
        $second = $exporter->expression('local', 'b/photo-9f8e7d6c.png', 'App\\Entity\\Card::image', $this->imports);

        self::assertSame($first, $second);
        self::assertSame(1, $exporter->count());
        self::assertSame(['App\\Entity\\Card::image', 'App\\Entity\\Hero::file'], $this->manifest->get(hash('sha256', 'same'))['fields'] ?? null);
        self::assertCount(1, $this->assets->listContents('fixtures')->toArray());
    }

    public function test_the_same_content_under_another_name_passes_its_own_name_so_its_reload_keeps_it(): void
    {
        $this->uploads->write('a/photo-0a1b2c3d.txt', 'same');
        $this->uploads->write('b/copy-9f8e7d6c.txt', 'same');
        $exporter = $this->exporter();
        $hash = hash('sha256', 'same');

        $exporter->expression('local', 'a/photo-0a1b2c3d.txt', 'App\\Entity\\Doc::file', $this->imports);

        self::assertSame(\sprintf("\$cwa->asset('%s', name: 'copy.txt')", $hash), $exporter->expression('local', 'b/copy-9f8e7d6c.txt', 'App\\Entity\\Doc::file', $this->imports));
        self::assertSame('photo.txt', $this->manifest->get($hash)['name'] ?? null);
    }

    public function test_a_file_already_in_the_store_is_not_written_again(): void
    {
        $this->uploads->write('a/photo-0a1b2c3d.txt', 'same');
        $store = $this->createMock(FilesystemOperator::class);
        $store->expects(self::once())->method('fileExists')->with('fixtures/' . hash('sha256', 'same'))->willReturn(true);
        $store->expects(self::never())->method('writeStream');

        (new ContentAddressedFileExporter($this->provider(), new FixtureAssetStore($store, 'fixtures', false, 'test'), $this->manifest, new PhpValueExporter()))
            ->expression('local', 'a/photo-0a1b2c3d.txt', 'App\\Entity\\Doc::file', $this->imports);

        self::assertSame(1, $this->manifest->count());
    }

    public function test_the_name_and_type_come_from_the_content(): void
    {
        $this->uploads->write('components/phpGPCPOl', (string) base64_decode(self::PNG, true));
        $this->uploads->write('notes', '');

        $this->exporter()->expression('local', 'components/phpGPCPOl', 'App\\Entity\\Hero::file', $this->imports);
        $this->exporter()->expression('local', 'notes', 'App\\Entity\\Doc::file', $this->imports);

        self::assertSame('phpgpcpol.png', $this->manifest->get(hash('sha256', (string) base64_decode(self::PNG, true)))['name'] ?? null);
        self::assertSame(['name' => 'notes', 'mimeType' => 'application/x-empty', 'size' => 0, 'fields' => ['App\\Entity\\Doc::file']], $this->manifest->get(hash('sha256', '')));
    }

    public function test_a_file_that_cannot_be_read_throws_a_filesystem_exception_and_records_nothing(): void
    {
        $exporter = $this->exporter();

        try {
            $exporter->expression('local', 'components/missing.png', 'App\\Entity\\Hero::file', $this->imports);
            self::fail('A missing file was exported.');
        } catch (FilesystemException) {
        }

        self::assertSame(0, $exporter->count());
        self::assertSame([], $this->assets->listContents('', true)->toArray());
    }

    public function test_a_store_that_cannot_be_written_fails_the_generation_rather_than_reporting_the_file(): void
    {
        $this->uploads->write('a/photo-0a1b2c3d.txt', 'same');
        $store = $this->createStub(FilesystemOperator::class);
        $store->method('fileExists')->willReturn(false);
        $store->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('fixtures/x', 'denied'));
        $exporter = new ContentAddressedFileExporter($this->provider(), new FixtureAssetStore($store, 'fixtures', false, 'the gcloud filesystem'), $this->manifest, new PhpValueExporter());

        try {
            $exporter->expression('local', 'a/photo-0a1b2c3d.txt', 'App\\Entity\\Doc::file', $this->imports);
            self::fail('A file the store refused was exported.');
        } catch (\RuntimeException $exception) {
            self::assertNotInstanceOf(FilesystemException::class, $exception);
            self::assertSame(\sprintf('The stored file "a/photo-0a1b2c3d.txt" could not be written to the fixture asset store (the gcloud filesystem) as %s: Unable to write file at location: fixtures/x. denied', hash('sha256', 'same')), $exception->getMessage());
            self::assertInstanceOf(UnableToWriteFile::class, $exception->getPrevious());
        }
        self::assertSame(0, $exporter->count());
    }

    public function test_the_stored_file_is_closed_after_it_is_exported_or_refused_by_the_store(): void
    {
        $source = fopen('php://memory', 'r+');
        fwrite($source, 'same');
        rewind($source);
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('readStream')->willReturn($source);
        $provider = new FilesystemProvider(new ServiceLocator(['local' => static fn () => new Filesystem($adapter)]));

        (new ContentAddressedFileExporter($provider, new FixtureAssetStore($this->assets, 'fixtures', false, 'test'), $this->manifest, new PhpValueExporter()))
            ->expression('local', 'a/photo-0a1b2c3d.txt', 'App\\Entity\\Doc::file', $this->imports);

        self::assertFalse(\is_resource($source));

        $refused = fopen('php://memory', 'r+');
        $adapter = $this->createStub(FilesystemAdapter::class);
        $adapter->method('readStream')->willReturn($refused);
        $store = $this->createStub(FilesystemOperator::class);
        $store->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('fixtures/x', 'denied'));
        try {
            (new ContentAddressedFileExporter(new FilesystemProvider(new ServiceLocator(['local' => static fn () => new Filesystem($adapter)])), new FixtureAssetStore($store, 'fixtures', false, 'test'), $this->manifest, new PhpValueExporter()))
                ->expression('local', 'b/other-0a1b2c3d.txt', 'App\\Entity\\Doc::file', $this->imports);
            self::fail('A file the store refused was exported.');
        } catch (\RuntimeException) {
        }
        self::assertFalse(\is_resource($refused));
    }

    private function exporter(): ContentAddressedFileExporter
    {
        return new ContentAddressedFileExporter($this->provider(), new FixtureAssetStore($this->assets, 'fixtures', false, 'test'), $this->manifest, new PhpValueExporter());
    }

    private function provider(): FilesystemProvider
    {
        return new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->uploads]));
    }
}
