<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\NavigationItemInterface;

/**
 * Class Tabs
 * @package Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 * @ORM\Entity()
 */
class Tabs extends AbstractNavigation
{
    public function createNavItem(): NavigationItemInterface
    {
        return new TabsItem();
    }
}
