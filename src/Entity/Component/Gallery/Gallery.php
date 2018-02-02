<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 *
 * @ApiResource()
 */
class Gallery extends AbstractComponent
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem", mappedBy="parent")
     * @ORM\OrderBy({"sort" = "ASC"})
     * @Groups({"component"})
     * @var GalleryItem
     */
    public $children;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
    }

    public function setChildren(array $children): void
    {
        $this->children = new ArrayCollection();
        foreach ($children as $child) {
            $this->addChild($child);
        }
    }

    public function addChild(GalleryItem $child): void
    {
        $this->children->add($child);
        $child->setParent($this);
    }

    public function removeChild(GalleryItem $child): void
    {
        $this->children->removeElement($child);
    }
}
