<?php

namespace Silverback\ApiComponentBundle\Serializer;

class FileNormalizer
{
    /**
     * @param string $filePath
     * @return bool
     */
    public function isImagineSupportedFile(?string $filePath): bool
    {
        if (!$filePath) {
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
