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

namespace Silverback\ApiComponentsBundle\Model\Uploadable;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadedDataUriFile extends UploadedFile
{
    /**
     * @param null $mimeType
     */
    public function __construct(DataUriFile $file, string $originalName = null, string $mimeType = null, int $error = null, bool $test = false)
    {
        parent::__construct($file->getPathname(), $originalName ?: $file->getFilename(), $mimeType, $error, $test);
    }
}
