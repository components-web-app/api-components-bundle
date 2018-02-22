<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Menu;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\NavigationItemInterface;

/**
 * Class Menu
 * @package Silverback\ApiComponentBundle\Entity\Component\Nav\Menu
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 * @ORM\Entity()
 */
class Menu extends AbstractNavigation
{
    public function createNavItem(): NavigationItemInterface
    {
        return new MenuItem();
    }
}
