<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Component;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class Gallery extends Component
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(GalleryItem::class);
    }
}
