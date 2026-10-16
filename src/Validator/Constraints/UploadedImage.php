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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraints\Image;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class UploadedImage extends Image
{
    public const int DEFAULT_MAX_PIXELS = 30_000_000;
    public const string SVG_NOT_ALLOWED_ERROR = 'a3a8f3a4-5d0e-4c1b-9b8e-2f6f1d4c7e21';

    private const float APPROXIMATE_RATIO = 1.5;

    protected const ERROR_NAMES = [
        self::SVG_NOT_ALLOWED_ERROR => 'SVG_NOT_ALLOWED_ERROR',
    ] + parent::ERROR_NAMES;

    public bool $allowSvg = true;
    public string $svgNotAllowedMessage = 'An SVG image is not allowed. Upload a raster image such as a PNG or JPEG.';
    public string $maxPixelsMessage = 'The image is too large ({{ pixels }} pixels). Resize it to at most {{ max_pixels }} pixels (about {{ max_width }} x {{ max_height }}) and upload it again.';

    #[HasNamedArguments]
    public function __construct(?bool $allowSvg = null, ?string $svgNotAllowedMessage = null, mixed ...$options)
    {
        parent::__construct(...$options);

        $this->allowSvg = $allowSvg ?? $this->allowSvg;
        $this->svgNotAllowedMessage = $svgNotAllowedMessage ?? $this->svgNotAllowedMessage;
    }

    public function forFile(bool $isSvg, int|float|null $defaultMaxPixels): Image
    {
        $resolved = clone $this;
        if ($isSvg) {
            $resolved->minWidth = $resolved->maxWidth = $resolved->minHeight = $resolved->maxHeight = null;
            $resolved->minPixels = $resolved->maxPixels = $resolved->minRatio = $resolved->maxRatio = null;
            $resolved->allowSquare = $resolved->allowLandscape = $resolved->allowPortrait = true;
            $resolved->detectCorrupted = false;

            return $resolved;
        }
        $resolved->maxPixels ??= $defaultMaxPixels;
        if (null !== $resolved->maxPixels) {
            $resolved->maxPixelsMessage = strtr($resolved->maxPixelsMessage, [
                '{{ max_width }}' => (string) floor(sqrt($resolved->maxPixels * self::APPROXIMATE_RATIO)),
                '{{ max_height }}' => (string) floor(sqrt($resolved->maxPixels / self::APPROXIMATE_RATIO)),
            ]);
        }

        return $resolved;
    }
}
