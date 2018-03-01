<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class GalleryItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class GalleryItem extends AbstractComponent implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $caption;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'filePath',
            new Assert\NotBlank()
        );
        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotBlank()
        );
    }

    /**
     * Gallery constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     * @return GalleryItem
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * @param null|string $caption
     * @return GalleryItem
     */
    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
        return $this;
    }
}
