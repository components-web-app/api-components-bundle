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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * Optional entity only mapped if imagine installed.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileInfo
{
    use IdTrait;

    public string $path;
    public string $mimeType;
    public int $fileSize;
    public ?int $width;
    public ?int $height;
    public ?string $filter;

    public function __construct(string $path, string $mimeType, int $fileSize, ?int $width, ?int $height, string $filter = null)
    {
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->width = $width;
        $this->height = $height;
        $this->fileSize = $fileSize;
        $this->filter = $filter;
    }
}
