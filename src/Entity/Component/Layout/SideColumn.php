<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Layout;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;

/**
 * Class Side Column
 * @package Silverback\ApiComponentBundle\Entity\Component\Gallery
 * @author Daniel West <daniel@silverback.is>
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
