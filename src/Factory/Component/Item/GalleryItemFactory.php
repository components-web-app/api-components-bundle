<?php

namespace Silverback\ApiComponentBundle\Factory\Component\Item;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;

class GalleryItemFactory
{
    private $manager;

    public function __construct(
        ObjectManager $manager
    ) {
        $this->manager = $manager;
    }

    public function createItem(
        string $image,
        string $title = null,
        ?string $caption = null
    ) : GalleryItem {
        $galleryItem = new GalleryItem();
        $galleryItem->setFilePath($image);
        $galleryItem->setTitle($title);
        $galleryItem->setCaption($caption);
        $this->manager->persist($galleryItem);
        return $galleryItem;
    }
}
