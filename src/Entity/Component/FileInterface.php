<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use Silverback\ApiComponentBundle\Dto\File\FileData;

interface FileInterface
{
    public function getFilePath(): ?string;

    /**
     * @param null|string $filePath
     * @return static
     */
    public function setFilePath(?string $filePath);

    /**
     * @return null|FileData
     */
    public function getFileData(): ?FileData;

    /**
     * @param null|FileData $fileData
     * @return static
     */
    public function setFileData(?FileData $fileData);

    public function getDir(): ?string;

    public static function getImagineFilters(): array;
}
