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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;

class StoredFileTest extends TestCase
{
    private const string ASSETS = __DIR__ . '/../../Functional/assets/files/';

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

    public function test_an_svg_without_both_dimensions_has_none_and_one_that_is_not_xml_is_read_as_symfony_reads_it(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('width-only.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10"></svg>');
        $filesystem->write('height-only.svg', '<svg xmlns="http://www.w3.org/2000/svg" height="10"></svg>');
        $filesystem->write('broken.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="20">');

        foreach (['width-only.svg', 'height-only.svg'] as $path) {
            self::assertNull((new StoredFile($filesystem, $path))->getDimensions(), $path);
        }
        self::assertSame([10, 20], (new StoredFile($filesystem, 'broken.svg'))->getDimensions());
    }

    public function test_an_svg_without_width_and_height_takes_them_from_its_view_box(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image_viewbox_only.svg', (string) file_get_contents(self::ASSETS . 'image_viewbox_only.svg'));
        $filesystem->write('width.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="50" viewBox="-10 -20 300 400.5"></svg>');
        $filesystem->write('height.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 400" height="60"></svg>');

        self::assertSame([840, 1200], (new StoredFile($filesystem, 'image_viewbox_only.svg'))->getDimensions());
        self::assertSame([50, 400.5], (new StoredFile($filesystem, 'width.svg'))->getDimensions());
        self::assertSame([300, 60], (new StoredFile($filesystem, 'height.svg'))->getDimensions());
    }

    /**
     * @return iterable<string, array{string, int|float}>
     */
    public static function svgLengths(): iterable
    {
        yield 'unitless integer' => ['12', 12];
        yield 'unitless integral decimal' => ['12.0', 12];
        yield 'unitless decimal' => ['12.5', 12.5];
        yield 'unitless decimal within rounding of an integer' => ['12.000000000001', 12.000000000001];
        yield 'exponent' => ['1.2e1', 12];
        yield 'pixels' => ['12px', 12];
        yield 'decimal pixels' => ['12.5PX', 12.5];
        yield 'inches' => ['2in', 192];
        yield 'upper case inches' => ['2IN', 192];
        yield 'centimetres' => ['2.54cm', 96];
        yield 'millimetres' => ['210mm', 210 * 96 / 25.4];
        yield 'millimetres converting to just under an integer' => ['25.4mm', 96];
        yield 'millimetres converting to just over an integer' => ['76.2mm', 288];
        yield 'points' => ['72pt', 96];
        yield 'picas' => ['2pc', 32];
        yield 'quarter millimetres' => ['101.6Q', 96];
        yield 'fractional quarter millimetres' => ['10q', 10 * 96 / 101.6];
    }

    #[DataProvider('svgLengths')]
    public function test_an_svg_length_is_converted_to_pixels_as_symfony_converts_it(string $length, int|float $pixels): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('a.svg', \sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 1 1"></svg>', $length, $length));

        self::assertSame([$pixels, $pixels], (new StoredFile($filesystem, 'a.svg'))->getDimensions());
    }

    public function test_a_percentage_or_unknown_length_falls_back_to_the_view_box_and_without_one_has_no_size(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image_percentage.svg', (string) file_get_contents(self::ASSETS . 'image_percentage.svg'));
        $filesystem->write('em.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10em" height="10" viewBox="0 0 30 40"></svg>');
        $filesystem->write('percentage-only.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>');
        $filesystem->write('zero.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="10"></svg>');
        $filesystem->write('zero-height.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="0mm"></svg>');

        self::assertSame([1200, 840], (new StoredFile($filesystem, 'image_percentage.svg'))->getDimensions());
        self::assertSame([30, 10], (new StoredFile($filesystem, 'em.svg'))->getDimensions());
        self::assertNull((new StoredFile($filesystem, 'percentage-only.svg'))->getDimensions());
        self::assertNull((new StoredFile($filesystem, 'zero.svg'))->getDimensions());
        self::assertNull((new StoredFile($filesystem, 'zero-height.svg'))->getDimensions());
    }

    public function test_an_svgs_cached_dimensions_are_ignored_because_they_were_read_from_its_attributes_alone(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image_viewbox_only.svg', (string) file_get_contents(self::ASSETS . 'image_viewbox_only.svg'));

        $file = new StoredFile($filesystem, 'image_viewbox_only.svg', ['mimeType' => StoredFile::SVG_MIME_TYPE, 'fileSize' => 1, 'width' => null, 'height' => null]);
        self::assertSame([840, 1200], $file->getDimensions());
        self::assertSame(1, $file->getSize());

        $file = new StoredFile($filesystem, 'image_viewbox_only.svg', ['mimeType' => StoredFile::SVG_MIME_TYPE, 'fileSize' => 1, 'width' => 5, 'height' => 5]);
        self::assertSame([840, 1200], $file->getDimensions());
    }

    public function test_an_svg_with_nul_bytes_read_from_the_filesystem_has_its_dimensions_when_the_cache_has_no_row(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('nul.svg', "<svg xmlns=\"http://www.w3.org/2000/svg\"\0 width=\"12\" height=\"34\"></svg>");
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('mimeType')->willReturn(StoredFile::SVG_MIME_TYPE);
        $filesystem->method('readStream')->willReturnCallback($inner->readStream(...));

        self::assertSame([12, 34], (new StoredFile($filesystem, 'nul.svg'))->getDimensions());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function zeroSizedImages(): iterable
    {
        yield 'no width' => [0, 5];
        yield 'no height' => [5, 0];
    }

    #[DataProvider('zeroSizedImages')]
    public function test_a_raster_image_with_a_zero_dimension_has_no_dimensions(int $width, int $height): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $chunk = 'IHDR' . pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $filesystem->write('empty.png', "\x89PNG\r\n\x1a\n" . pack('N', 13) . $chunk . pack('N', crc32($chunk)) . str_repeat("\0", 32));

        self::assertNull((new StoredFile($filesystem, 'empty.png'))->getDimensions());
    }

    public function test_reading_dimensions_closes_the_stream_it_read_from(): void
    {
        $inner = new Filesystem(new InMemoryFilesystemAdapter());
        $inner->write('image.png', (string) file_get_contents(self::ASSETS . 'image.png'));
        $streams = [];
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('mimeType')->willReturn('image/png');
        $filesystem->method('readStream')->willReturnCallback(static function (string $path) use ($inner, &$streams) {
            return $streams[] = $inner->readStream($path);
        });

        self::assertSame([500, 715], (new StoredFile($filesystem, 'image.png'))->getDimensions());
        self::assertCount(1, $streams);
        self::assertFalse(\is_resource($streams[0]));
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
