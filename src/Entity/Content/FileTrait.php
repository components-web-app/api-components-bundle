<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait FileTrait
 * @package Silverback\ApiComponentBundle\Entity\Content\Component
 */
trait FileTrait
{
    /**
     * @ORM\Column(type="string", nullable=false)
     * @Assert\File()
     * @Groups({"component", "content", "route"})
     * @ApiProperty(iri="http://schema.org/contentUrl")
     * @var null|string
     */
    protected $filePath;

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
            'thumbnailPath' => 'thumbnail',
            'placeholderPath' => 'placeholder_square'
        ];
    }
}
