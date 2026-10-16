<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use League\Flysystem\FilesystemOperator;

final class StoredFile
{
    public const string SVG_MIME_TYPE = 'image/svg+xml';
    private const string SVG_NUMBER = '-?[0-9]*\.?[0-9]+(?:e[+-]?[0-9]+)?';
    private const string SVG_LENGTH = self::SVG_NUMBER . '(?i:px|in|cm|mm|pt|pc|q)?';
    private const string SVG_WIDTH = '/<svg[^<>]+width="(?<width>' . self::SVG_LENGTH . ')"[^<>]*>/';
    private const string SVG_HEIGHT = '/<svg[^<>]+height="(?<height>' . self::SVG_LENGTH . ')"[^<>]*>/';
    private const string SVG_VIEW_BOX = '/<svg[^<>]+viewBox="' . self::SVG_NUMBER . ' ' . self::SVG_NUMBER . ' (?<width>' . self::SVG_NUMBER . ') (?<height>' . self::SVG_NUMBER . ')"[^<>]*>/';

    private ?string $mimeType = null;
    private ?int $size = null;
    /** @var array{int|float, int|float}|false|null */
    private array|false|null $dimensions = null;

    /**
     * @param array{mimeType: string, fileSize: int, width: ?int, height: ?int}|null $fileInfo
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly string $path,
        ?array $fileInfo = null,
    ) {
        if (null !== $fileInfo) {
            $this->mimeType = $fileInfo['mimeType'];
            $this->size = $fileInfo['fileSize'];
            if (!$this->isSvg()) {
                $this->dimensions = null !== $fileInfo['width'] && null !== $fileInfo['height'] ? [$fileInfo['width'], $fileInfo['height']] : false;
            }
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function getMimeType(): string
    {
        return $this->mimeType ??= $this->filesystem->mimeType($this->path);
    }

    public function getSize(): int
    {
        return $this->size ??= $this->filesystem->fileSize($this->path);
    }

    public function isSvg(): bool
    {
        return self::SVG_MIME_TYPE === $this->getMimeType();
    }

    /**
     * @return array{int|float, int|float}|null
     */
    public function getDimensions(): ?array
    {
        $this->dimensions ??= $this->readDimensions() ?? false;

        return $this->dimensions ?: null;
    }

    /**
     * @return array{int|float, int|float}|null
     */
    private function readDimensions(): ?array
    {
        $source = $this->filesystem->readStream($this->path);
        $copy = tmpfile();
        try {
            stream_copy_to_stream($source, $copy);
            $localPath = stream_get_meta_data($copy)['uri'];

            return $this->isSvg() ? $this->svgDimensions((string) file_get_contents($localPath)) : $this->rasterDimensions($localPath);
        } finally {
            fclose($copy);
            fclose($source);
        }
    }

    /**
     * @return array{int, int}|null
     */
    private function rasterDimensions(string $localPath): ?array
    {
        $size = @getimagesize($localPath);
        if (false === $size || 0 === $size[0] || 0 === $size[1]) {
            return null;
        }

        return [$size[0], $size[1]];
    }

    /**
     * @return array{int|float, int|float}|null
     */
    private function svgDimensions(string $content): ?array
    {
        if (preg_match(self::SVG_WIDTH, $content, $widthMatches)) {
            $width = self::svgLengthToPixels($widthMatches['width']);
        }
        if (preg_match(self::SVG_HEIGHT, $content, $heightMatches)) {
            $height = self::svgLengthToPixels($heightMatches['height']);
        }
        if (preg_match(self::SVG_VIEW_BOX, $content, $viewBoxMatches)) {
            $width ??= self::svgLengthToPixels($viewBoxMatches['width']);
            $height ??= self::svgLengthToPixels($viewBoxMatches['height']);
        }
        if (!isset($width, $height) || 0 === $width || 0 === $height) {
            return null;
        }

        return [$width, $height];
    }

    private static function svgLengthToPixels(string $length): int|float
    {
        $value = (float) $length;
        if (is_numeric($length)) {
            return (int) $length == $value ? (int) $length : $value;
        }

        $value = match (strtolower(substr($length, -2))) {
            'in' => $value * 96,
            'cm' => $value * 96 / 2.54,
            'mm' => $value * 96 / 25.4,
            'pt' => $value * 96 / 72,
            'pc' => $value * 16,
            default => 'q' === strtolower(substr($length, -1)) ? $value * 96 / 101.6 : $value,
        };
        $rounded = round($value);

        return abs($value - $rounded) < 1e-10 ? (int) $rounded : $value;
    }
}
