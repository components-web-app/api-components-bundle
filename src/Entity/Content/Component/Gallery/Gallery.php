<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Gallery;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class Gallery
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"routePrefix"="/component"})
 * @ORM\Entity()
 */
class Gallery extends AbstractComponent
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(GalleryItem::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
