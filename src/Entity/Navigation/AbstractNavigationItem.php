<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\SortableTrait;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigationItem
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 */
abstract class AbstractNavigationItem implements NavigationItemInterface
{
    use SortableTrait;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var AbstractNavigation
     * @Groups({"component_item_write"})
     */
    protected $navigation;

    /**
     * @Groups({"layout", "component", "component_item"})
     * @var string
     */
    protected $label;

    /**
     * @Groups({"layout", "component", "component_item"})
     * @var null|Route
     */
    protected $route;

    /**
     * @Groups({"layout", "component", "component_item"})
     * @var null|string
     */
    protected $fragment;

    /**
     * @Groups({"layout", "component", "component_item"})
     * @var null|AbstractNavigation
     */
    protected $child;

    /**
     * AbstractNavigationItem constructor.
     */
    public function __construct() {
        $this->id = Uuid::uuid4()->getHex();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return AbstractNavigation
     */
    public function getNavigation(): AbstractNavigation
    {
        return $this->navigation;
    }

    /**
     * @param AbstractNavigation $navigation
     */
    public function setNavigation(AbstractNavigation $navigation): void
    {
        $this->navigation = $navigation;
    }


    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return AbstractNavigationItem
     */
    public function setLabel(string $label): AbstractNavigationItem
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @param null|Route $route
     * @return AbstractNavigationItem
     */
    public function setRoute(?Route $route): AbstractNavigationItem
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    /**
     * @param null|string $fragment
     * @return AbstractNavigationItem
     */
    public function setFragment(?string $fragment): AbstractNavigationItem
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * @return null|AbstractNavigation
     */
    public function getChild(): ?AbstractNavigation
    {
        return $this->child;
    }

    /**
     * @param null|AbstractNavigation $child
     * @return AbstractNavigationItem
     */
    public function setChild(?AbstractNavigation $child): AbstractNavigationItem
    {
        $this->child = $child;
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|Collection|\Silverback\ApiComponentBundle\Entity\Component\SortableInterface[]|AbstractNavigationItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->navigation->getItems();
    }
}
