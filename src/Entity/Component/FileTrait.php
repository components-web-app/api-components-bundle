<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\DTO\File\FileData;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trait FileTrait
 * @package Silverback\ApiComponentBundle\Entity\Component
 */
trait FileTrait
{
    /**
     * We are not asserting this is a file here because it may be a string for dynamic component e.g. {{ filePath }}
     * validation constraint could be made perhaps to validate a file or a variable
     * @ORM\Column(type="string", nullable=false)
     * @Groups({"component", "content"})
     * @ApiProperty(iri="http://schema.org/contentUrl")
     * @var null|string
     */
    protected $filePath;

    /**
     * @Groups({"component", "content"})
     * @var \Silverback\ApiComponentBundle\DTO\File\FileData|null
     */
    private $fileData;

    /**
     * @return null|string
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * @param null|string $filePath
     */
    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public static function getImagineFilters(): array
    {
        return [
            'thumbnail' => 'thumbnail',
            'placeholderSquare' => 'placeholder_square',
            'placeholder' => 'placeholder'
        ];
    }

    public function getDir(): ?string
    {
        return null;
    }

    /**
     * @return null|\Silverback\ApiComponentBundle\DTO\File\FileData
     */
    public function getFileData(): ?FileData
    {
        return $this->fileData;
    }

    /**
     * @param null|\Silverback\ApiComponentBundle\File\Model\\Silverback\ApiComponentBundle\DTO\File\FileData $fileData
     */
    public function setFileData(?FileData $fileData): void
    {
        $this->fileData = $fileData;
    }
}
