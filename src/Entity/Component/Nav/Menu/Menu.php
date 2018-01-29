<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Menu;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNav;
use Silverback\ApiComponentBundle\Entity\Component\Nav\NavItemInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource()
 * @ORM\Entity()
 */
class Menu extends AbstractNav
{
    /**
     * @ORM\OneToMany(targetEntity="MenuItem", mappedBy="nav")
     * @ORM\OrderBy({"sortOrder" = "ASC"})
     * @Groups({"layout", "page"})
     */
    protected $items;

    public function createNavItem(): NavItemInterface
    {
        return new MenuItem();
    }
}
