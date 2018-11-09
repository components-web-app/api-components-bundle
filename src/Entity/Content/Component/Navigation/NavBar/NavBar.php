<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * @author Daniel West <daniel@silverback.is
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
