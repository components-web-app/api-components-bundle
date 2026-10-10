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

    private ?string $mimeType = null;
    private ?int $size = null;
    /** @var array{int, int}|false|null */
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
            $this->dimensions = null !== $fileInfo['width'] && null !== $fileInfo['height'] ? [$fileInfo['width'], $fileInfo['height']] : false;
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
     * @return array{int, int}|null
     */
    public function getDimensions(): ?array
    {
        $this->dimensions ??= $this->readDimensions() ?? false;

        return $this->dimensions ?: null;
    }

    /**
     * @return array{int, int}|null
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
     * @return array{int, int}|null
     */
    private function svgDimensions(string $content): ?array
    {
        $svg = @simplexml_load_string(str_replace("\0", '', $content));
        if (false === $svg) {
            return null;
        }
        $width = (int) $svg['width'];
        $height = (int) $svg['height'];
        if (0 === $width || 0 === $height) {
            return null;
        }

        return [$width, $height];
    }
}
