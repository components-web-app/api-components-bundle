<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\NavigationItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

class Tabs extends AbstractNavigation
{
    /**
     * @ORM\OneToMany(targetEntity="TabItem", mappedBy="nav")
     * @ORM\OrderBy({"sort" = "ASC"})
     * @Groups({"layout", "page"})
     */
    protected $items;

    public function createNavItem(): NavigationItemInterface
    {
        return new TabItem();
    }
}
