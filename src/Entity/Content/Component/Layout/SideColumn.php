<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Layout;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class Side Column
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class SideColumn extends AbstractComponent
{
    public function __construct()
    {
        parent::__construct();
        $this->addComponentGroup(new ComponentGroup($this));
        $this->addComponentGroup(new ComponentGroup($this));
    }
}
