<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImage;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImageValidator;
use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UploadedImageValidatorTest extends TestCase
{
    private const string ASSETS = __DIR__ . '/../../../features/assets/files/';

    public function test_an_image_over_the_pixel_limit_is_told_the_limit_and_an_approximate_size(): void
    {
        self::assertSame(
            [[Image::TOO_MANY_PIXEL_ERROR, 'The image is too large (357500 pixels). Resize it to at most 357499 pixels (about 732 x 488) and upload it again.']],
            $this->validate('image.png', new UploadedImage(maxPixels: 357499)),
        );
    }

    public function test_an_image_within_the_pixel_limit_is_valid(): void
    {
        self::assertSame([], $this->validate('image.png', new UploadedImage(maxPixels: 357500)));
    }

    public function test_a_custom_max_pixels_message_is_kept(): void
    {
        self::assertSame(
            [[Image::TOO_MANY_PIXEL_ERROR, 'Too big: 357500 of 357499 (about 732 x 488).']],
            $this->validate('image.png', new UploadedImage(maxPixels: 357499, maxPixelsMessage: 'Too big: {{ pixels }} of {{ max_pixels }} (about {{ max_width }} x {{ max_height }}).')),
        );
    }

    public function test_the_pixel_limit_defaults_to_the_configured_one(): void
    {
        self::assertSame(
            [[Image::TOO_MANY_PIXEL_ERROR, 'The image is too large (357500 pixels). Resize it to at most 357499 pixels (about 732 x 488) and upload it again.']],
            $this->validate('image.png', new UploadedImage(), new UploadedImageValidator(357499)),
        );
        self::assertSame([], $this->validate('image.png', new UploadedImage(maxPixels: 357500), new UploadedImageValidator(357499)));
    }

    public function test_the_default_pixel_limit_is_thirty_megapixels(): void
    {
        self::assertSame(30_000_000, UploadedImage::DEFAULT_MAX_PIXELS);
        self::assertSame([], $this->validate('image.png', new UploadedImage(), new UploadedImageValidator()));
        self::assertSame(
            [[Image::TOO_MANY_PIXEL_ERROR, 'The image is too large (357500 pixels). Resize it to at most 1000 pixels (about 38 x 25) and upload it again.']],
            $this->validate('image.png', new UploadedImage(maxPixels: 1000), new UploadedImageValidator()),
        );
    }

    public function test_no_configured_limit_and_no_option_checks_no_pixels(): void
    {
        self::assertSame([], $this->validate('image.png', new UploadedImage(), new UploadedImageValidator(null)));
    }

    public function test_the_other_image_options_are_checked_as_image_checks_them(): void
    {
        self::assertSame(
            [[Image::TOO_WIDE_ERROR, 'The image width is too big (500px). Allowed maximum width is 499px.']],
            $this->validate('image.png', new UploadedImage(maxWidth: 499)),
        );
        self::assertSame(
            [[File::INVALID_MIME_TYPE_ERROR, 'The mime type of the file is invalid ("image/png"). Allowed mime types are "image/jpeg".']],
            $this->validate('image.png', new UploadedImage(mimeTypes: ['image/jpeg'])),
        );
    }

    public function test_an_svg_skips_the_image_checks_it_has_no_pixels_for(): void
    {
        self::assertSame([], $this->validate('unsized.svg', new UploadedImage(maxPixels: 1, minWidth: 10, maxRatio: 0.1, allowSquare: false, detectCorrupted: true)));
        self::assertSame([], $this->validate('image.svg', new UploadedImage(maxPixels: 1, allowPortrait: false)));
    }

    public function test_an_svg_still_gets_the_file_checks(): void
    {
        self::assertSame(
            [[File::TOO_LARGE_ERROR, 'The file is too large (95 bytes). Allowed maximum size is 10 bytes.']],
            $this->validate('unsized.svg', new UploadedImage(maxSize: 10)),
        );
    }

    public function test_an_svg_is_refused_when_svg_is_not_allowed(): void
    {
        self::assertSame(
            [[UploadedImage::SVG_NOT_ALLOWED_ERROR, 'An SVG image is not allowed. Upload a raster image such as a PNG or JPEG.']],
            $this->validate('image.svg', new UploadedImage(allowSvg: false)),
        );
        self::assertSame('SVG_NOT_ALLOWED_ERROR', UploadedImage::getErrorName(UploadedImage::SVG_NOT_ALLOWED_ERROR));
        self::assertSame('TOO_MANY_PIXEL_ERROR', UploadedImage::getErrorName(Image::TOO_MANY_PIXEL_ERROR));
        self::assertSame([], $this->validate('image.png', new UploadedImage(allowSvg: false)));
    }

    public function test_a_refused_svg_gets_no_other_violation(): void
    {
        self::assertSame(
            [[UploadedImage::SVG_NOT_ALLOWED_ERROR, 'No SVG here.']],
            $this->validate('image.svg', new UploadedImage(allowSvg: false, svgNotAllowedMessage: 'No SVG here.', mimeTypes: ['image/png'], maxSize: 10)),
        );
    }

    public function test_an_svg_given_as_a_path_is_recognised(): void
    {
        $violations = $this->validator()->validate(self::ASSETS . 'unsized.svg', new UploadedImage(maxPixels: 1));

        self::assertCount(0, $violations);
    }

    public function test_no_file_is_valid(): void
    {
        self::assertCount(0, $this->validator()->validate(null, new UploadedImage(allowSvg: false, maxPixels: 1)));
        self::assertCount(0, $this->validator()->validate('', new UploadedImage(allowSvg: false, maxPixels: 1)));
    }

    public function test_the_constraint_is_not_changed_by_validating(): void
    {
        $constraint = new UploadedImage(minWidth: 10);
        $this->validate('unsized.svg', $constraint, new UploadedImageValidator(357499));

        self::assertNull($constraint->maxPixels);
        self::assertSame(10, $constraint->minWidth);
        self::assertStringContainsString('{{ max_width }}', $constraint->maxPixelsMessage);
    }

    public function test_only_an_uploaded_image_constraint_is_validated(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        (new UploadedImageValidator())->validate(new FileObject(self::ASSETS . 'image.png'), new NotBlank());
    }

    /**
     * @return list<array{string|null, string}>
     */
    private function validate(string $asset, UploadedImage $constraint, ?UploadedImageValidator $validator = null): array
    {
        $summary = [];
        foreach ($this->validator($validator)->validate(new FileObject(self::ASSETS . $asset), $constraint) as $violation) {
            $summary[] = [$violation->getCode(), (string) $violation->getMessage()];
        }

        return $summary;
    }

    private function validator(?UploadedImageValidator $validator = null): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([UploadedImageValidator::class => $validator ?? new UploadedImageValidator()]))
            ->getValidator();
    }
}
