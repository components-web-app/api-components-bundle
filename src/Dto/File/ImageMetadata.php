<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Dto\File;

use Silverback\ApiComponentBundle\Exception\FileMissingException;
use Silverback\ApiComponentBundle\Exception\FileNotImageException;
use function exif_imagetype;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ImageMetadata
{
    private int $width;
    private int $height;
    private string $filePath;
    private string $publicPath;
    private ?string $imagineKey;

    public function __construct(
        string $filePath,
        string $publicPath,
        ?string $imagineKey = null
    ) {
        $this->filePath = $filePath;
        $this->publicPath = $publicPath;

        if (!file_exists($filePath)) {
            throw new FileMissingException(sprintf('The file %s does not exist while constructing %s', $filePath, self::class));
        }

        if (mime_content_type($filePath) === 'image/svg+xml') {
            $xmlGet = simplexml_load_string(file_get_contents($filePath));
            $xmlAttributes = $xmlGet->attributes();
            $this->width = (int) $xmlAttributes->width;
            $this->height = (int) $xmlAttributes->height;
        } else {
            if (false === exif_imagetype($filePath)) {
                throw new FileNotImageException(sprintf('The file %s is not an image while constructing %s', $filePath, self::class));
            }

            [$this->width, $this->height] = getimagesize($filePath);
            $this->imagineKey = $imagineKey;
        }
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function getImagineKey(): ?string
    {
        return $this->imagineKey;
    }
}
