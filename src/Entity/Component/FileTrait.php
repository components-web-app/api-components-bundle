<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trait FileTrait
 * @package Silverback\ApiComponentBundle\Entity\Component
 */
trait FileTrait
{
    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
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
