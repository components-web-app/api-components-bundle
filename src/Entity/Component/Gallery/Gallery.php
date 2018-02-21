<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class Gallery extends AbstractComponent
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(GalleryItem::class);
        // New galleries should have a component group added by default for the gallery images/items
        $this->addComponentGroup(new ComponentGroup());
    }
}
