<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Menu;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation;
use Silverback\ApiComponentBundle\Entity\Navigation\NavigationItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *
 * @ApiResource()
 */
class Menu extends AbstractNavigation
{
    /**
     * @ORM\OneToMany(targetEntity="MenuItem", mappedBy="nav")
     * @ORM\OrderBy({"sort" = "ASC"})
     * @Groups({"layout", "page"})
     */
    protected $items;

    public function createNavItem(): NavigationItemInterface
    {
        return new MenuItem();
    }
}
