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

namespace Silverback\ApiComponentBundle\Dto\File;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class FileData
{
    private ?string $publicPath;
    private ?string $fileExtension;
    private ?int $fileSize;
    private ?ImageMetadata $imageData;
    private ?array $imagineData;

    public function __construct(
        ?string $publicPath,
        ?ImageMetadata $imageData,
        ?array $imagineData,
        ?string $fileExtension,
        ?int $fileSize
    ) {
        $this->publicPath = $publicPath;
        $this->imageData = $imageData;
        $this->imagineData = $imagineData;
        $this->fileExtension = $fileExtension;
        $this->fileSize = $fileSize;
    }

    public function getPublicPath(): ?string
    {
        return $this->publicPath;
    }

    public function getImageData(): ?ImageMetadata
    {
        return $this->imageData;
    }

    public function getImagineData(): ?array
    {
        return $this->imagineData;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function getFileSize(): ?float
    {
        return $this->fileSize;
    }
}
