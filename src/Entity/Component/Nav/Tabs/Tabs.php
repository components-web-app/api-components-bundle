<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\NavigationItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

class Tabs extends AbstractNavigation
{
    /**
     * @Groups({"layout", "page"})
     */
    protected $items;

    public function createNavItem(): NavigationItemInterface
    {
        return new TabItem();
    }
}
