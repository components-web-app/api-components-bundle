<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;

class GalleryFactory extends AbstractComponentFactory
{
    public function getComponent(): Component
    {
        return new GalleryItem();
    }
}
