<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;

class StoredFileTest extends TestCase
{
    private const string ASSETS = __DIR__ . '/../../../features/assets/files/';

    public function test_cached_file_info_is_used_without_reading_the_object(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method(self::anything());

        $file = new StoredFile($filesystem, 'nested/photo-0a1b2c3d.jpg', ['mimeType' => 'image/jpeg', 'fileSize' => 2048, 'width' => 640, 'height' => 480]);

        self::assertSame('nested/photo-0a1b2c3d.jpg', $file->getPath());
        self::assertSame('photo-0a1b2c3d.jpg', $file->getFilename());
        self::assertSame('image/jpeg', $file->getMimeType());
        self::assertSame(2048, $file->getSize());
        self::assertSame([640, 480], $file->getDimensions());
        self::assertFalse($file->isSvg());
    }

    public function test_cached_file_info_without_dimensions_means_none_were_detected_and_the_object_is_not_read(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::never())->method(self::anything());

        self::assertNull((new StoredFile($filesystem, 'a.pdf', ['mimeType' => 'application/pdf', 'fileSize' => 1, 'width' => null, 'height' => null]))->getDimensions());
        self::assertNull((new StoredFile($filesystem, 'a.png', ['mimeType' => 'image/png', 'fileSize' => 1, 'width' => 10, 'height' => null]))->getDimensions());
        self::assertNull((new StoredFile($filesystem, 'a.png', ['mimeType' => 'image/png', 'fileSize' => 1, 'width' => null, 'height' => 10]))->getDimensions());
    }

    public function test_without_file_info_the_metadata_is_read_from_the_filesystem_once(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('image.png', (string) file_get_contents(self::ASSETS . 'image.png'));
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())->method('mimeType')->with('image.png')->willReturnCallback($inner->mimeType(...));
        $filesystem->expects(self::once())->method('fileSize')->with('image.png')->willReturnCallback($inner->fileSize(...));
        $filesystem->expects(self::once())->method('readStream')->with('image.png')->willReturnCallback($inner->readStream(...));

        $file = new StoredFile($filesystem, 'image.png');

        self::assertSame('image/png', $file->getMimeType());
        self::assertSame('image/png', $file->getMimeType());
        self::assertSame(3467, $file->getSize());
        self::assertSame(3467, $file->getSize());
        self::assertSame([500, 715], $file->getDimensions());
        self::assertSame([500, 715], $file->getDimensions());
    }

    public function test_an_svgs_dimensions_are_its_width_and_height_attributes(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image.svg', (string) file_get_contents(self::ASSETS . 'image.svg'));

        $file = new StoredFile($filesystem, 'image.svg');

        self::assertTrue($file->isSvg());
        self::assertSame([840, 1200], $file->getDimensions());
    }

    public function test_an_svg_without_both_dimensions_or_that_is_not_xml_has_none(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('width-only.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10"></svg>');
        $filesystem->write('height-only.svg', '<svg xmlns="http://www.w3.org/2000/svg" height="10"></svg>');
        $filesystem->write('broken.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">');

        foreach (['width-only.svg', 'height-only.svg', 'broken.svg'] as $path) {
            self::assertNull((new StoredFile($filesystem, $path, ['mimeType' => StoredFile::SVG_MIME_TYPE, 'fileSize' => 1, 'width' => null, 'height' => null]))->getDimensions(), $path);
            self::assertNull((new StoredFile($filesystem, $path))->getDimensions(), $path);
        }
    }

    public function test_an_svg_read_from_the_filesystem_has_its_dimensions_when_the_cache_has_no_row(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('nul.svg', "<svg xmlns=\"http://www.w3.org/2000/svg\"\0 width=\"12\" height=\"34\"></svg>");

        self::assertSame([12, 34], (new StoredFile($filesystem, 'nul.svg'))->getDimensions());
    }

    public function test_a_file_that_is_not_an_image_has_no_dimensions_and_is_read_only_once(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('notes.txt', 'not an image');
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('mimeType')->willReturnCallback($inner->mimeType(...));
        $filesystem->expects(self::once())->method('readStream')->willReturnCallback($inner->readStream(...));

        $file = new StoredFile($filesystem, 'notes.txt');

        self::assertNull($file->getDimensions());
        self::assertNull($file->getDimensions());
    }
}
