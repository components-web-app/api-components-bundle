<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class Gallery extends Component
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem", mappedBy="gallery")
     * @ORM\OrderBy({"sortOrder" = "ASC"})
     * @Groups({"page"})
     */
    protected $items;

    /**
     * AbstractFeature constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems(array $items): void
    {
        $this->items = new ArrayCollection();
        foreach($items as $item)
        {
            $this->addItem($item);
        }
    }

    /**
     * @param GalleryItem $item
     */
    public function addItem(GalleryItem $item): void
    {
        $this->items->add($item);
        $item->setGallery($this);
    }

    /**
     * @param GalleryItem $item
     */
    public function removeItem(GalleryItem $item): void
    {
        $this->items->removeElement($item);
    }
}
