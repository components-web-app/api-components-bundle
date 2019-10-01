<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Dto\File;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FileData
 * @package Silverback\ApiComponentBundle\Entity\Content
 * This class is to hold data about a file and is added into a File component during serialization
 */
class FileData
{
    /**
     * @Groups({"default"})
     * @var string|null
     */
    private $publicPath;

    /**
     * @Groups({"default"})
     * @var string|null
     */
    private $fileExtension;

    /**
     * @Groups({"default"})
     * @var int|null
     */
    private $fileSize;

    /**
     * @Groups({"default"})
     * @var ImageMetadata|null
     */
    private $imageData;

    /**
     * @Groups({"default"})
     * @var ImageMetadata[]|null
     */
    private $imagineData;

    public function __construct(
        ?string $publicPath,
        ?ImageMetadata $imageData,
        ?array $imagineData,
        ?string $fileExtension,
        ?int $fileSize
    )
    {
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
