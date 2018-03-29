<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Menu;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\AbstractNavigationItem;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class MenuItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Menu
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"routePrefix"="/component"})
 * @ORM\Entity()
 */
class MenuItem extends AbstractNavigationItem
{
    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @Groups({"content", "component"})
     * @var bool
     */
    private $menuLabel = false;

    /**
     * @return bool
     */
    public function isMenuLabel(): bool
    {
        return $this->menuLabel;
    }

    /**
     * @param bool $menuLabel
     */
    public function setMenuLabel(bool $menuLabel): void
    {
        $this->menuLabel = $menuLabel;
    }
}
