<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Menu;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class Menu
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Menu
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class Menu extends AbstractNavigation
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(MenuItem::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
