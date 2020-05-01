<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

use League\Flysystem\Filesystem;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MediaObjectFactory
{
    public function create(object $object, Filesystem $filesystem, string $filename, string $imagineFilter = null): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = 'https://www.website.com/path';
        $mediaObject->fileSize = $this->convertSizeToString($filesystem->fileSize($filename));
        $mediaObject->mimeType = $filesystem->mimeType($filename);
        $mediaObject->imagineFilter = $imagineFilter;

        if (false !== strpos($mediaObject->mimeType, 'image/')) {
            $file = $filesystem->read($filename);
            if ('image/svg+xml' === $mediaObject->mimeType) {
                $xmlget = simplexml_load_string(file_get_contents($file));
                $xmlattributes = $xmlget->attributes();
                $mediaObject->width = (int) $xmlattributes->width;
                $mediaObject->height = (int) $xmlattributes->height;
            } else {
                [ $mediaObject->width, $mediaObject->height ] = @getimagesize($file);
            }
        }

        return $mediaObject;
    }

    private function convertSizeToString(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . 'GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . 'MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . 'KB';
        }

        return $bytes . 'B';
    }

    private function fileIsImage($filePath): bool
    {
        return exif_imagetype($filePath) || 'image/svg+xml' === mime_content_type($filePath);
    }
}
