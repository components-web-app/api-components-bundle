<?php

namespace Silverback\ApiComponentBundle\DTO\File;

use Silverback\ApiComponentBundle\DTO\File\ImageMetadata;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FileData
 * @package Silverback\ApiComponentBundle\Entity\Content
 * This class is to hold data about a file and is added into a File component during serialization
 */
class FileData
{
    /**
     * @Groups({"component", "content"})
     * @var string|null
     */
    private $publicPath;

    /**
     * @Groups({"component", "content"})
     * @var \Silverback\ApiComponentBundle\DTO\File\ImageMetadata|null
     */
    private $imageData;

    /**
     * @Groups({"component", "content"})
     * @var \Silverback\ApiComponentBundle\DTO\File\ImageMetadata[]|null
     */
    private $imagineData;

    /**
     * FileData constructor.
     * @param null|string $publicPath
     * @param null|\Silverback\ApiComponentBundle\DTO\File\ImageMetadata $imageData
     * @param null|\Silverback\ApiComponentBundle\DTO\File\ImageMetadata[] $imagineData
     */
    public function __construct(?string $publicPath, ?ImageMetadata $imageData, ?array $imagineData)
    {
        $this->publicPath = $publicPath;
        $this->imageData = $imageData;
        $this->imagineData = $imagineData;
    }

    /**
     * @return null|string
     */
    public function getPublicPath(): ?string
    {
        return $this->publicPath;
    }

    /**
     * @return null|\Silverback\ApiComponentBundle\DTO\File\ImageMetadata
     */
    public function getImageData(): ?ImageMetadata
    {
        return $this->imageData;
    }

    /**
     * @return null|\Silverback\ApiComponentBundle\DTO\File\ImageMetadata[]
     */
    public function getImagineData(): ?array
    {
        return $this->imagineData;
    }
}
