<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class NavBar
 * @package Silverback\ApiComponentBundle\Entity\Layout\NavBar
 * @author Daniel West <daniel@silverback.is
 * @ApiResource()
 * @ORM\Entity()
 */
class NavBar extends AbstractNavigation
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(NavBarItem::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
