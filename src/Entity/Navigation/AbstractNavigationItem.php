<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use ApiPlatform\Core\Annotation\ApiSubresource;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\SortableTrait;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigationItem
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
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
     */
    protected $navigation;

    /**
     * @Groups({"layout", "component"})
     * @var string
     */
    protected $label;

    /**
     * @Groups({"layout", "component"})
     * @var null|Route
     */
    protected $route;

    /**
     * @Groups({"layout", "component"})
     * @var null|string
     */
    protected $fragment;

    /**
     * @ApiSubresource()
     * @Groups({"layout", "component"})
     * @var null|AbstractNavigation
     */
    protected $child;

    public function __construct(
        AbstractNavigation $navigation,
        string $label,
        ?Route $route = null,
        ?string $fragment = null,
        ?AbstractNavigation $child = null
    ) {
        $this->id = Uuid::uuid4()->getHex();
        $this->navigation = $navigation;
        $this
            ->setLabel($label)
            ->setRoute($route)
            ->setFragment($fragment)
            ->setChild($child)
        ;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @return null|string
     */
    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    /**
     * @return null|AbstractNavigation
     */
    public function getChild(): ?AbstractNavigation
    {
        return $this->child;
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
     * @param null|Route $route
     * @return AbstractNavigationItem
     */
    public function setRoute(?Route $route): AbstractNavigationItem
    {
        $this->route = $route;
        return $this;
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
     * @param null|AbstractNavigation $child
     * @return AbstractNavigationItem
     */
    public function setChild(?AbstractNavigation $child): AbstractNavigationItem
    {
        $this->child = $child;
        return $this;
    }
}
