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

namespace Silverback\ApiComponentBundle\Validator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineSupportedFilePath
{
    public static function isValidFilePath(?string $filePath): bool
    {
        if (!$filePath || !file_exists($filePath)) {
            return false;
        }

        try {
            $imageType = exif_imagetype($filePath);
        } catch (\Exception $e) {
            return false;
        }

        return \in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000, IMAGETYPE_JP2, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }
}
