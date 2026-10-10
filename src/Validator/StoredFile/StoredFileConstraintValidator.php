<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\StoredFile;

use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class StoredFileConstraintValidator extends ConstraintValidator
{
    private const int KB_BYTES = 1000;
    private const int MB_BYTES = 1000000;
    private const int KIB_BYTES = 1024;
    private const int MIB_BYTES = 1048576;
    private const array SUFFICES = [
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof File) {
            throw new UnexpectedTypeException($constraint, File::class);
        }
        if (null === $value) {
            return;
        }
        if (!$value instanceof StoredFile) {
            throw new UnexpectedValueException($value, StoredFile::class);
        }
        if (!$this->validateFile($value, $constraint) || !$constraint instanceof Image) {
            return;
        }
        $this->validateImage($value, $constraint);
    }

    private function validateFile(StoredFile $file, File $constraint): bool
    {
        $size = $file->getSize();
        if (0 === $size) {
            $this->context->buildViolation($constraint->disallowEmptyMessage)
                ->setParameter('{{ file }}', $this->formatValue($file->getPath()))
                ->setParameter('{{ name }}', $this->formatValue($file->getFilename()))
                ->setCode(File::EMPTY_ERROR)
                ->addViolation();

            return false;
        }

        if ($constraint->maxSize && $size > $constraint->maxSize) {
            [$sizeAsString, $limitAsString, $suffix] = $this->factorizeSizes($size, $constraint->maxSize, (bool) $constraint->binaryFormat);
            $this->context->buildViolation($constraint->maxSizeMessage)
                ->setParameter('{{ file }}', $this->formatValue($file->getPath()))
                ->setParameter('{{ size }}', $sizeAsString)
                ->setParameter('{{ limit }}', $limitAsString)
                ->setParameter('{{ suffix }}', $suffix)
                ->setParameter('{{ name }}', $this->formatValue($file->getFilename()))
                ->setCode(File::TOO_LARGE_ERROR)
                ->addViolation();

            return false;
        }

        $mimeTypes = (array) $constraint->mimeTypes;
        if (!$mimeTypes) {
            return true;
        }
        $mime = $file->getMimeType();
        foreach ($mimeTypes as $mimeType) {
            if ($mimeType === $mime) {
                return true;
            }
            $discrete = strstr($mimeType, '/*', true);
            if ($discrete && strstr($mime, '/', true) === $discrete) {
                return true;
            }
        }
        $this->context->buildViolation($constraint->mimeTypesMessage)
            ->setParameter('{{ file }}', $this->formatValue($file->getPath()))
            ->setParameter('{{ type }}', $this->formatValue($mime))
            ->setParameter('{{ types }}', $this->formatValues($mimeTypes))
            ->setParameter('{{ name }}', $this->formatValue($file->getFilename()))
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->addViolation();

        return false;
    }

    private function validateImage(StoredFile $file, Image $constraint): void
    {
        if (null === $constraint->minWidth && null === $constraint->maxWidth
            && null === $constraint->minHeight && null === $constraint->maxHeight
            && null === $constraint->minPixels && null === $constraint->maxPixels
            && null === $constraint->minRatio && null === $constraint->maxRatio
            && $constraint->allowSquare && $constraint->allowLandscape && $constraint->allowPortrait) {
            return;
        }

        $dimensions = $file->getDimensions();
        if (null === $dimensions) {
            $this->context->buildViolation($constraint->sizeNotDetectedMessage)
                ->setCode(Image::SIZE_NOT_DETECTED_ERROR)
                ->addViolation();

            return;
        }
        [$width, $height] = $dimensions;

        if (!$file->isSvg() && !$this->validateRasterSize($constraint, $width, $height)) {
            return;
        }

        $this->validateShape($constraint, $width, $height);
    }

    private function validateRasterSize(Image $constraint, int $width, int $height): bool
    {
        if ($constraint->minWidth && $width < $constraint->minWidth) {
            $this->context->buildViolation($constraint->minWidthMessage)
                ->setParameter('{{ width }}', (string) $width)
                ->setParameter('{{ min_width }}', (string) $constraint->minWidth)
                ->setCode(Image::TOO_NARROW_ERROR)
                ->addViolation();

            return false;
        }
        if ($constraint->maxWidth && $width > $constraint->maxWidth) {
            $this->context->buildViolation($constraint->maxWidthMessage)
                ->setParameter('{{ width }}', (string) $width)
                ->setParameter('{{ max_width }}', (string) $constraint->maxWidth)
                ->setCode(Image::TOO_WIDE_ERROR)
                ->addViolation();

            return false;
        }
        if ($constraint->minHeight && $height < $constraint->minHeight) {
            $this->context->buildViolation($constraint->minHeightMessage)
                ->setParameter('{{ height }}', (string) $height)
                ->setParameter('{{ min_height }}', (string) $constraint->minHeight)
                ->setCode(Image::TOO_LOW_ERROR)
                ->addViolation();

            return false;
        }
        if ($constraint->maxHeight && $height > $constraint->maxHeight) {
            $this->context->buildViolation($constraint->maxHeightMessage)
                ->setParameter('{{ height }}', (string) $height)
                ->setParameter('{{ max_height }}', (string) $constraint->maxHeight)
                ->setCode(Image::TOO_HIGH_ERROR)
                ->addViolation();
        }

        $pixels = $width * $height;
        if (null !== $constraint->minPixels && $pixels < $constraint->minPixels) {
            $this->context->buildViolation($constraint->minPixelsMessage)
                ->setParameter('{{ pixels }}', (string) $pixels)
                ->setParameter('{{ min_pixels }}', (string) $constraint->minPixels)
                ->setParameter('{{ height }}', (string) $height)
                ->setParameter('{{ width }}', (string) $width)
                ->setCode(Image::TOO_FEW_PIXEL_ERROR)
                ->addViolation();
        }
        if (null !== $constraint->maxPixels && $pixels > $constraint->maxPixels) {
            $this->context->buildViolation($constraint->maxPixelsMessage)
                ->setParameter('{{ pixels }}', (string) $pixels)
                ->setParameter('{{ max_pixels }}', (string) $constraint->maxPixels)
                ->setParameter('{{ height }}', (string) $height)
                ->setParameter('{{ width }}', (string) $width)
                ->setCode(Image::TOO_MANY_PIXEL_ERROR)
                ->addViolation();
        }

        return true;
    }

    private function validateShape(Image $constraint, int $width, int $height): void
    {
        $ratio = round($width / $height, 2);
        if (null !== $constraint->minRatio && $ratio < round((float) $constraint->minRatio, 2)) {
            $this->context->buildViolation($constraint->minRatioMessage)
                ->setParameter('{{ ratio }}', (string) $ratio)
                ->setParameter('{{ min_ratio }}', (string) round((float) $constraint->minRatio, 2))
                ->setCode(Image::RATIO_TOO_SMALL_ERROR)
                ->addViolation();
        }
        if (null !== $constraint->maxRatio && $ratio > round((float) $constraint->maxRatio, 2)) {
            $this->context->buildViolation($constraint->maxRatioMessage)
                ->setParameter('{{ ratio }}', (string) $ratio)
                ->setParameter('{{ max_ratio }}', (string) round((float) $constraint->maxRatio, 2))
                ->setCode(Image::RATIO_TOO_BIG_ERROR)
                ->addViolation();
        }
        if (!$constraint->allowSquare && $width === $height) {
            $this->addShapeViolation($constraint->allowSquareMessage, Image::SQUARE_NOT_ALLOWED_ERROR, $width, $height);
        }
        if (!$constraint->allowLandscape && $width > $height) {
            $this->addShapeViolation($constraint->allowLandscapeMessage, Image::LANDSCAPE_NOT_ALLOWED_ERROR, $width, $height);
        }
        if (!$constraint->allowPortrait && $width < $height) {
            $this->addShapeViolation($constraint->allowPortraitMessage, Image::PORTRAIT_NOT_ALLOWED_ERROR, $width, $height);
        }
    }

    private function addShapeViolation(string $message, string $code, int $width, int $height): void
    {
        $this->context->buildViolation($message)
            ->setParameter('{{ width }}', (string) $width)
            ->setParameter('{{ height }}', (string) $height)
            ->setCode($code)
            ->addViolation();
    }

    /**
     * @return array{string, string, string}
     */
    private function factorizeSizes(int $size, int|float $limit, bool $binaryFormat): array
    {
        $coef = $binaryFormat ? self::MIB_BYTES : self::MB_BYTES;
        $coefFactor = $binaryFormat ? self::KIB_BYTES : self::KB_BYTES;

        while ($limit < $coef) {
            $coef /= $coefFactor;
        }

        $limitAsString = (string) ($limit / $coef);
        while (self::moreDecimalsThan($limitAsString, 2)) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
        }

        $sizeAsString = (string) round($size / $coef, 2);
        while ($sizeAsString === $limitAsString) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
            $sizeAsString = (string) round($size / $coef, 2);
        }

        return [$sizeAsString, $limitAsString, self::SUFFICES[$coef]];
    }

    private static function moreDecimalsThan(string $double, int $numberOfDecimals): bool
    {
        return \strlen($double) > \strlen((string) round((float) $double, $numberOfDecimals));
    }
}
