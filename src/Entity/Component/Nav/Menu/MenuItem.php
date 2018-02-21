<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav\Menu;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem;
use Symfony\Component\Serializer\Annotation\Groups;

class MenuItem extends AbstractNavigationItem
{
    /**
     * @ORM\ManyToOne(targetEntity="Menu", inversedBy="items")
     * @var Menu
     */
    protected $nav;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     * @Groups({"page"})
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
