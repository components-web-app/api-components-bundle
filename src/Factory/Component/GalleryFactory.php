<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;

class GalleryFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new Gallery();
    }
}
