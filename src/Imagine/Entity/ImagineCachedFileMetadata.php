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

namespace Silverback\ApiComponentsBundle\Imagine\Entity;

use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * Optional entity only mapped if imagine installed.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineCachedFileMetadata
{
    use IdTrait;

    public string $filter;
    public string $path;
    public string $mimeType;
    public int $width;
    public int $height;
    public int $fileSize;

    public function __construct(string $filter, string $path, string $mimeType, int $width, int $height, int $fileSize)
    {
        $this->setId();
        $this->filter = $filter;
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->width = $width;
        $this->height = $height;
        $this->fileSize = $fileSize;
    }
}
