<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\StoredFile;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImage;
use Silverback\ApiComponentsBundle\Validator\StoredFile\StoredFileConstraintValidator;
use Silverback\ApiComponentsBundle\Validator\StoredFile\StoredFileConstraintValidatorFactory;
use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StoredFileConstraintValidatorTest extends TestCase
{
    private const string ASSETS = __DIR__ . '/../../Functional/assets/files/';

    /**
     * @return iterable<string, array{string, Constraint}>
     */
    public static function constraintsSymfonyAlsoChecks(): iterable
    {
        $constraints = [
            'no options' => new File(),
            'max size above' => new File(maxSize: 3467),
            'max size below in bytes' => new File(maxSize: 3466),
            'max size below in kB' => new File(maxSize: '3k'),
            'max size below in KiB' => new File(maxSize: '3Ki'),
            'max size below in MB' => new File(maxSize: 1000, binaryFormat: false),
            'max size below binary' => new File(maxSize: 1500, binaryFormat: true),
            'mime type exact' => new File(mimeTypes: ['image/png']),
            'mime type wildcard' => new File(mimeTypes: ['image/*']),
            'mime type other' => new File(mimeTypes: ['image/jpeg', 'application/*']),
            'mime type string' => new File(mimeTypes: 'image/jpeg'),
            'image no options' => new Image(),
            'image max width' => new Image(maxWidth: 499),
            'image max width equal' => new Image(maxWidth: 500),
            'image min width' => new Image(minWidth: 501),
            'image max height' => new Image(maxHeight: 714),
            'image min height' => new Image(minHeight: 716),
            'image max pixels' => new Image(maxPixels: 357499),
            'image max pixels equal' => new Image(maxPixels: 357500),
            'image min pixels' => new Image(minPixels: 357501),
            'image max ratio' => new Image(maxRatio: 0.5),
            'image min ratio' => new Image(minRatio: 0.8),
            'image min width equal' => new Image(minWidth: 500),
            'image min height equal' => new Image(minHeight: 715),
            'image max height equal' => new Image(maxHeight: 715),
            'image min pixels equal' => new Image(minPixels: 357500),
            'image min ratio rounded to the ratio' => new Image(minRatio: 0.704),
            'image max ratio rounded to the ratio' => new Image(maxRatio: 0.696),
            'image no mime types still checks dimensions' => new Image(mimeTypes: [], maxWidth: 499),
            'image exact mime type still checks dimensions' => new Image(mimeTypes: ['image/png', 'image/svg+xml', 'text/plain'], maxWidth: 499),
            'image min height stops shape checks' => new Image(minHeight: 716, maxRatio: 0.1, allowPortrait: false),
            'image no portrait' => new Image(allowPortrait: false),
            'image no landscape' => new Image(allowLandscape: false),
            'image no square' => new Image(allowSquare: false),
            'image width stops further checks' => new Image(maxWidth: 499, maxPixels: 1, maxRatio: 0.1),
            'image min width stops further checks' => new Image(minWidth: 501, maxPixels: 1),
            'image min height stops further checks' => new Image(minHeight: 716, maxPixels: 1),
            'image height does not stop further checks' => new Image(maxHeight: 714, maxPixels: 1, minPixels: 1000000, minRatio: 2, allowPortrait: false),
            'image too large file' => new Image(maxSize: 10, maxPixels: 1),
            'image wrong type' => new Image(mimeTypes: ['image/jpeg'], maxPixels: 1),
            'uploaded image no options' => new UploadedImage(),
            'uploaded image max pixels' => new UploadedImage(maxPixels: 357499),
            'uploaded image max pixels equal' => new UploadedImage(maxPixels: 357500),
            'uploaded image dimension options' => new UploadedImage(maxPixels: 1, minWidth: 10000, maxRatio: 0.1, allowPortrait: false),
            'uploaded image too large file' => new UploadedImage(maxSize: 10, maxPixels: 1),
            'uploaded image wrong type' => new UploadedImage(mimeTypes: ['image/jpeg']),
            'uploaded image no svg' => new UploadedImage(allowSvg: false, maxPixels: 1),
            'uploaded image no svg and other options' => new UploadedImage(allowSvg: false, svgNotAllowedMessage: 'No SVG here.', mimeTypes: ['image/jpeg'], maxSize: 10),
        ];
        foreach (['image.png', 'image.svg', 'unsized.svg', 'image_viewbox_only.svg', 'image_length_units.svg', 'image_percentage.svg', 'test_file.txt'] as $asset) {
            foreach ($constraints as $name => $constraint) {
                yield \sprintf('%s: %s', $asset, $name) => [$asset, $constraint];
            }
        }
    }

    #[DataProvider('constraintsSymfonyAlsoChecks')]
    public function test_a_stored_file_gets_the_violations_symfony_gives_the_same_file_on_disk(string $asset, Constraint $constraint): void
    {
        if ('image_length_units.svg' === $asset && !method_exists(ImageValidator::class, 'convertSvgLengthToPixels')) {
            self::markTestSkipped('This symfony/validator reads no SVG length units; the scan reads them as later releases do.');
        }
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write($asset, (string) file_get_contents(self::ASSETS . $asset));

        $expected = $this->summarise(Validation::createValidator()->validate(new FileObject(self::ASSETS . $asset), $constraint));
        $actual = $this->summarise($this->validator()->validate(new StoredFile($filesystem, $asset), $constraint));

        self::assertSame($expected, $actual);
    }

    public function test_an_uploaded_image_without_max_pixels_takes_the_configured_limit(): void
    {
        $file = $this->cached('image/png', 10, 500, 715);
        $message = 'The image is too large (357500 pixels). Resize it to at most 357499 pixels (about 732 x 488) and upload it again.';

        self::assertSame([[Image::TOO_MANY_PIXEL_ERROR, $message]], $this->summarise($this->validator(357499)->validate($file, new UploadedImage())));
        self::assertSame([], $this->summarise($this->validator(357499)->validate($file, new UploadedImage(maxPixels: 357500))));
        self::assertSame([], $this->summarise($this->validator(null)->validate($file, new UploadedImage())));
        self::assertSame([], $this->summarise($this->validator()->validate($file, new UploadedImage())));
        self::assertSame([], $this->summarise($this->validator(357499)->validate($file, new Image())));
    }

    public function test_an_empty_file_is_a_violation_as_symfony_reports_it(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('empty.png', '');
        $path = tempnam(sys_get_temp_dir(), 'acb');
        self::assertIsString($path);

        try {
            $expected = $this->summarise(Validation::createValidator()->validate(new FileObject($path), new Image(maxPixels: 1)));
        } finally {
            unlink($path);
        }

        self::assertSame([[File::EMPTY_ERROR, 'An empty file is not allowed.']], $expected);
        self::assertSame($expected, $this->summarise($this->validator()->validate(new StoredFile($filesystem, 'empty.png'), new Image(maxPixels: 1))));
    }

    public function test_an_image_whose_size_cannot_be_detected_is_a_violation_only_when_a_dimension_option_is_set(): void
    {
        $file = $this->cached('image/png', 10, null, null);

        self::assertSame([], $this->summarise($this->validator()->validate($file, new Image())));
        self::assertSame([[Image::SIZE_NOT_DETECTED_ERROR, 'The size of the image could not be detected.']], $this->summarise($this->validator()->validate($file, new Image(maxPixels: 1))));
        self::assertSame([[Image::SIZE_NOT_DETECTED_ERROR, 'The size of the image could not be detected.']], $this->summarise($this->validator()->validate($file, new Image(allowSquare: false))));
        self::assertSame([[Image::SIZE_NOT_DETECTED_ERROR, 'The size of the image could not be detected.']], $this->summarise($this->validator()->validate($file, new Image(allowLandscape: false))));
        self::assertSame([[Image::SIZE_NOT_DETECTED_ERROR, 'The size of the image could not be detected.']], $this->summarise($this->validator()->validate($file, new Image(allowPortrait: false))));
    }

    public function test_a_square_image_is_reported_when_squares_are_not_allowed(): void
    {
        $file = $this->cached('image/png', 10, 40, 40);

        self::assertSame([[Image::SQUARE_NOT_ALLOWED_ERROR, 'The image is square (40x40px). Square images are not allowed.']], $this->summarise($this->validator()->validate($file, new Image(allowSquare: false))));
        self::assertSame([], $this->summarise($this->validator()->validate($file, new Image(allowLandscape: false, allowPortrait: false))));
    }

    public function test_a_landscape_image_is_reported_when_landscape_is_not_allowed(): void
    {
        $file = $this->cached('image/png', 10, 41, 40);

        self::assertSame([[Image::LANDSCAPE_NOT_ALLOWED_ERROR, 'The image is landscape oriented (41x40px). Landscape oriented images are not allowed.']], $this->summarise($this->validator()->validate($file, new Image(allowLandscape: false))));
        self::assertSame([], $this->summarise($this->validator()->validate($file, new Image(allowSquare: false, allowPortrait: false))));
    }

    public function test_the_ratio_limits_compare_at_two_decimal_places(): void
    {
        $file = $this->cached('image/png', 10, 1001, 1000);

        self::assertSame([], $this->summarise($this->validator()->validate($file, new Image(maxRatio: 1.004, minRatio: 0.996))));
        self::assertSame([[Image::RATIO_TOO_BIG_ERROR, 'The image ratio is too big (1). Allowed maximum ratio is 0.99.']], $this->summarise($this->validator()->validate($file, new Image(maxRatio: 0.994))));
        self::assertSame([[Image::RATIO_TOO_SMALL_ERROR, 'The image ratio is too small (1). Minimum ratio expected is 1.01.']], $this->summarise($this->validator()->validate($file, new Image(minRatio: 1.005))));
    }

    public function test_a_size_limit_under_a_kilobyte_is_shown_in_bytes(): void
    {
        $file = $this->cached('image/png', 1500, 1, 1);

        self::assertSame([[File::TOO_LARGE_ERROR, 'The file is too large (1500 bytes). Allowed maximum size is 999 bytes.']], $this->summarise($this->validator()->validate($file, new File(maxSize: 999))));
        self::assertSame([[File::TOO_LARGE_ERROR, 'The file is too large (1.5 kB). Allowed maximum size is 1.25 kB.']], $this->summarise($this->validator()->validate($file, new File(maxSize: 1250))));
        self::assertSame([[File::TOO_LARGE_ERROR, 'The file is too large (1501 bytes). Allowed maximum size is 1500 bytes.']], $this->summarise($this->validator()->validate($this->cached('image/png', 1501, 1, 1), new File(maxSize: 1500))));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sizesAndLimits(): iterable
    {
        yield 'a limit with too many decimals in the larger unit' => [2000000, 1234500];
        yield 'a size that rounds to the limit and has decimals in the smaller unit' => [1004567, 1000000];
        yield 'a size that rounds to the limit in every larger unit' => [1000001, 1000000];
    }

    #[DataProvider('sizesAndLimits')]
    public function test_a_too_large_file_is_reported_in_the_units_symfony_chooses(int $size, int $maxSize): void
    {
        $path = tempnam(sys_get_temp_dir(), 'acb');
        self::assertIsString($path);
        try {
            $handle = fopen($path, 'r+');
            self::assertIsResource($handle);
            ftruncate($handle, $size);
            fclose($handle);
            $expected = $this->summarise(Validation::createValidator()->validate(new FileObject($path), new File(maxSize: $maxSize)));
        } finally {
            unlink($path);
        }

        self::assertCount(1, $expected);
        self::assertSame($expected, $this->summarise($this->validator()->validate($this->cached('image/png', $size, 1, 1), new File(maxSize: $maxSize))));
    }

    public function test_a_null_value_is_valid_and_anything_else_that_is_not_a_stored_file_is_refused(): void
    {
        self::assertCount(0, $this->validator()->validate(null, new File(maxSize: 1)));
        (new StoredFileConstraintValidator())->validate(null, new File(maxSize: 1));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(StoredFile::class);
        (new StoredFileConstraintValidator())->validate('a.png', new File(maxSize: 1));
    }

    public function test_only_file_constraints_are_validated(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new StoredFileConstraintValidator())->validate($this->cached('image/png', 1, 1, 1), new NotBlank());
    }

    private function cached(string $mimeType, int $size, ?int $width, ?int $height): StoredFile
    {
        return new StoredFile($this->createStub(FilesystemOperator::class), 'a.png', ['mimeType' => $mimeType, 'fileSize' => $size, 'width' => $width, 'height' => $height]);
    }

    private function validator(int|float|null $defaultMaxPixels = UploadedImage::DEFAULT_MAX_PIXELS): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->setConstraintValidatorFactory(new StoredFileConstraintValidatorFactory(defaultMaxPixels: $defaultMaxPixels))->getValidator();
    }

    /**
     * @return list<array{string|null, string}>
     */
    private function summarise(ConstraintViolationListInterface $violations): array
    {
        $summary = [];
        foreach ($violations as $violation) {
            $summary[] = [$violation->getCode(), (string) $violation->getMessage()];
        }

        return $summary;
    }
}
