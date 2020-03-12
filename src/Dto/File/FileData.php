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
    private ?ImagineMetadata $imagineData;

    public function __construct(
        ?string $publicPath,
        ?string $fileExtension,
        ?int $fileSize,
        ?ImageMetadata $imageData,
        ?ImagineMetadata $imagineData
    ) {
        $this->publicPath = $publicPath;
        $this->fileExtension = $fileExtension;
        $this->fileSize = $fileSize;
        $this->imageData = $imageData;
        $this->imagineData = $imagineData;
    }

    public function getPublicPath(): ?string
    {
        return $this->publicPath;
    }

    public function getImageData(): ?ImageMetadata
    {
        return $this->imageData;
    }

    public function getImagineData(): ?ImagineMetadata
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
