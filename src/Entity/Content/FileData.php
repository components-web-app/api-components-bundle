<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Silverback\ApiComponentBundle\Serializer\ImageMetadata;
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
     * @var ImageMetadata|null
     */
    private $imageData;

    /**
     * @Groups({"component", "content"})
     * @var ImageMetadata[]|null
     */
    private $imagineData;

    /**
     * FileData constructor.
     * @param null|string $publicPath
     * @param null|ImageMetadata $imageData
     * @param null|ImageMetadata[] $imagineData
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
     * @return null|ImageMetadata
     */
    public function getImageData(): ?ImageMetadata
    {
        return $this->imageData;
    }

    /**
     * @return null|ImageMetadata[]
     */
    public function getImagineData(): ?array
    {
        return $this->imagineData;
    }
}
