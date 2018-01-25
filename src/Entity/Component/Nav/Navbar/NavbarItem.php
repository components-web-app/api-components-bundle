<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Navbar;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNavItem;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ApiResource()
 * @ORM\Entity()
 */
class NavbarItem extends AbstractNavItem
{
    /**
     * @ORM\ManyToOne(targetEntity="Navbar", inversedBy="items")
     * @var Navbar
     */
    protected $nav;
}
