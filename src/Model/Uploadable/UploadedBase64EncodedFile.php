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

namespace Silverback\ApiComponentBundle\Model\Uploadable;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Shota Hoshino <lga0503@gmail.com>
 * @author Daniel West <daniel@silverback.is>
 */
class UploadedBase64EncodedFile extends UploadedFile
{
    /**
     * @param string $originalName
     * @param null   $mimeType
     * @param null   $size
     */
    public function __construct(Base64EncodedFile $file, $originalName = '', $mimeType = null, $size = null)
    {
        $method = new \ReflectionMethod(parent::class, '__construct');
        $num = $method->getNumberOfParameters();
        if (5 === $num) {
            parent::__construct($file->getPathname(), $originalName ?: $file->getFilename(), $mimeType, null, true);
        } else {
            // Symfony 4 compatible
            parent::__construct($file->getPathname(), $originalName ?: $file->getFilename(), $mimeType, $size, null, true);
        }
    }
}
