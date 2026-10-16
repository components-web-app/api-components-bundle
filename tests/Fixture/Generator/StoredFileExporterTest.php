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
use League\Flysystem\FilesystemException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\StoredFileExporter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

class StoredFileExporterTest extends TestCase
{
    private Filesystem $filesystem;
    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->directory = sys_get_temp_dir() . '/cwa-stored-file-exporter-' . bin2hex(random_bytes(6)) . '/assets';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/{,.}*', \GLOB_BRACE) ?: [] as $file) {
            if (\in_array(basename($file), ['.', '..'], true)) {
                continue;
            }
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
            rmdir(\dirname($this->directory));
        }
    }

    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_a_file_is_copied_without_its_token(): void
    {
        $this->filesystem->write('components/home-hero-0a1b2c3d.png', base64_decode(self::PNG, true));

        $name = $this->exporter()->export('local', 'components/home-hero-0a1b2c3d.png');

        self::assertSame('home-hero.png', $name);
        self::assertSame(base64_decode(self::PNG, true), file_get_contents($this->directory . '/home-hero.png'));
    }

    public function test_a_file_stored_under_a_php_temp_name_is_exported_under_the_name_its_upload_would_be_given(): void
    {
        $this->filesystem->write('phpGPCPOl', base64_decode(self::PNG, true));

        self::assertSame('phpgpcpol.png', $this->exporter()->export('local', 'phpGPCPOl'));
        self::assertSame(['phpgpcpol.png'], $this->exportedFiles());
    }

    public function test_the_extension_comes_from_the_content_type(): void
    {
        $this->filesystem->write('a/notes-0a1b2c3d', 'plain text');
        $this->filesystem->write('b/Photo-0a1b2c3d.jpg', base64_decode(self::PNG, true));
        $this->filesystem->write('c/data-0a1b2c3d.md', 'plain text');
        $exporter = $this->exporter();

        self::assertSame(['notes.txt', 'photo.png', 'data.md'], [
            $exporter->export('local', 'a/notes-0a1b2c3d'),
            $exporter->export('local', 'b/Photo-0a1b2c3d.jpg'),
            $exporter->export('local', 'c/data-0a1b2c3d.md'),
        ]);
    }

    public function test_names_that_would_collide_get_a_number_that_survives_a_reload(): void
    {
        $this->filesystem->write('a/image-0a1b2c3d.png', base64_decode(self::PNG, true));
        $this->filesystem->write('b/image-9f8e7d6c.png', base64_decode(self::PNG, true));
        $this->filesystem->write('c/IMAGE', base64_decode(self::PNG, true));
        $this->filesystem->write('d/phpGPCPOl', base64_decode(self::PNG, true));
        $this->filesystem->write('e/phpgpcpol-2-0a1b2c3d.png', base64_decode(self::PNG, true));
        $exporter = $this->exporter();

        self::assertSame(['image.png', 'image-2.png', 'image-3.png', 'phpgpcpol.png', 'phpgpcpol-2.png'], [
            $exporter->export('local', 'a/image-0a1b2c3d.png'),
            $exporter->export('local', 'b/image-9f8e7d6c.png'),
            $exporter->export('local', 'c/IMAGE'),
            $exporter->export('local', 'd/phpGPCPOl'),
            $exporter->export('local', 'e/phpgpcpol-2-0a1b2c3d.png'),
        ]);
        self::assertSame(5, $exporter->count());
        self::assertSame(['image-2.png', 'image-3.png', 'image.png', 'phpgpcpol-2.png', 'phpgpcpol.png'], $this->exportedFiles());
    }

    public function test_a_name_that_is_only_a_token_keeps_a_stem(): void
    {
        $this->filesystem->write('-0a1b2c3d.png', base64_decode(self::PNG, true));

        self::assertSame('file.png', $this->exporter()->export('local', '-0a1b2c3d.png'));
    }

    public function test_a_file_that_cannot_be_read_throws_and_creates_no_directory(): void
    {
        $exporter = $this->exporter();

        try {
            $exporter->export('local', 'components/missing.png');
            self::fail('A missing file was exported.');
        } catch (FilesystemException) {
        }

        self::assertDirectoryDoesNotExist($this->directory);
        self::assertSame(0, $exporter->count());
    }

    /**
     * @return list<string>
     */
    private function exportedFiles(): array
    {
        $files = array_map(basename(...), glob($this->directory . '/{,.}*', \GLOB_BRACE) ?: []);

        return array_values(array_filter($files, static fn (string $file): bool => !\in_array($file, ['.', '..'], true)));
    }

    private function exporter(): StoredFileExporter
    {
        return new StoredFileExporter(new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem])), $this->directory);
    }
}
