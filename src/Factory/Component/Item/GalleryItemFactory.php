<?php

namespace Silverback\ApiComponentBundle\Factory\Component\Item;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;
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
        Gallery $gallery,
        string $image,
        string $title = null,
        ?string $caption = null,
        int $order = null
    ) : GalleryItem {
        if (null === $order) {
            $lastItem = $gallery->getItems()->last();
            if (!$lastItem) {
                $order = 0;
            } else {
                $order = $lastItem->getSort() + 1;
            }
        }
        $galleryItem = new GalleryItem();
        $galleryItem->setFilePath($image);
        $galleryItem->setSort($order);
        $galleryItem->setTitle($title);
        $galleryItem->setCaption($caption);
        $gallery->addItem($galleryItem);
        $this->manager->persist($galleryItem);
        return $galleryItem;
    }
}
