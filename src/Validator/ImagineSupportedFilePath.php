<?php

namespace Silverback\ApiComponentBundle\Validator;

class ImagineSupportedFilePath
{
    public static function isValidFilePath(?string $filePath): bool
    {
        if (!$filePath || !file_exists($filePath)) {
            return false;
        }
        try {
            $imageType = \exif_imagetype($filePath);
        } catch (\Exception $e) {
            return false;
        }
        return \in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }
}
