<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;

class GalleryComponent extends AbstractComponent
{
    public function getComponent(): Component
    {
        return new Gallery();
    }
}
