<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UploadedImageValidator extends ImageValidator
{
    private const string SVG_MIME_TYPE = 'image/svg+xml';

    public function __construct(private readonly int|float|null $defaultMaxPixels = UploadedImage::DEFAULT_MAX_PIXELS)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UploadedImage) {
            throw new UnexpectedTypeException($constraint, UploadedImage::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        $isSvg = $this->isSvg($value);
        if ($isSvg && !$constraint->allowSvg) {
            $this->context->buildViolation($constraint->svgNotAllowedMessage)
                ->setCode(UploadedImage::SVG_NOT_ALLOWED_ERROR)
                ->addViolation();

            return;
        }

        parent::validate($value, $constraint->forFile($isSvg, $this->defaultMaxPixels));
    }

    private function isSvg(mixed $value): bool
    {
        $mimeType = $value instanceof File ? $value->getMimeType() : MimeTypes::getDefault()->guessMimeType((string) $value);

        return self::SVG_MIME_TYPE === $mimeType;
    }
}
