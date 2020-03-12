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

namespace Silverback\ApiComponentBundle\Entity\Utility;

use Silverback\ApiComponentBundle\Dto\File\FileData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface FileInterface
{
    public function getFilePath(): ?string;

    public function setFilePath(?string $filePath);

    public function setFileData(FileData $fileData);

    public function getFileData(): ?FileData;

    public static function getImagineFilters(): array;

    public function getDirectory(): ?string;
}
