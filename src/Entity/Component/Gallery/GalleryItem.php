<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponentItem;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 */
class GalleryItem extends AbstractComponentItem implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank()
     * @Groups({"component"})
     * @var null|string
     */
    protected $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"component"})
     * @var null|string
     */
    protected $caption;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery", inversedBy="children")
     * @Groups({"component_write"})
     * @var Gallery
     */
    public $parent;

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
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
     */
    public function setCaption(?string $caption): void
    {
        $this->caption = $caption;
    }

    public function getSortCollection(): Collection
    {
        return $this->parent->getChildren();
    }
}
