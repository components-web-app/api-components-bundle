<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Silverback\ApiComponentBundle\File\FileData;

interface FileInterface
{
    public function getFilePath(): ?string;

    public function setFilePath(?string $filePath): void;

    public static function getImagineFilters(): array;

    public function getDir(): ?string;

    public function getFileData(): ?FileData;

    public function setFileData(?FileData $fileData): void;
}
