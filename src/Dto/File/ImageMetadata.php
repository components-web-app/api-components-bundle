<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    private int $width = 0;
    private int $height = 0;
    private string $url;

    public function __construct(
        string $filePath,
        string $url
    ) {
        if (!file_exists($filePath)) {
            throw new FileMissingException(sprintf('The file %s does not exist while constructing %s', $filePath, self::class));
        }

        $this->url = $url;

        if ('image/svg+xml' === mime_content_type($filePath)) {
            $xmlGet = simplexml_load_string(file_get_contents($filePath));
            $xmlAttributes = $xmlGet->attributes();
            $this->width = (int) $xmlAttributes->width;
            $this->height = (int) $xmlAttributes->height;
        } else {
            if (false === exif_imagetype($filePath)) {
                throw new FileNotImageException(sprintf('The file %s is not an image while constructing %s', $filePath, self::class));
            }

            [$this->width, $this->height] = getimagesize($filePath);
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

    public function getUrl(): string
    {
        return $this->url;
    }
}
