<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use Silverback\ApiComponentBundle\Entity\Component\SortableInterface;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\Route;

/**
 * Interface NavigationItemInterface
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 */
interface NavigationItemInterface extends SortableInterface
{
    /**
     * NavigationItemInterface constructor.
     * @param AbstractNavigation $navigation
     * @param string $label
     * @param null|Route $route
     * @param null|string $fragment
     * @param null|AbstractNavigation $child
     */
    public function __construct(
        AbstractNavigation $navigation,
        string $label,
        ?Route $route = null,
        ?string $fragment = null,
        ?AbstractNavigation $child = null
    );

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route;

    /**
     * @return null|string
     */
    public function getFragment(): ?string;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @return null|AbstractNavigation
     */
    public function getChild(): ?AbstractNavigation;

    /**
     * @param null|Route $route
     * @return AbstractNavigationItem
     */
    public function setRoute(?Route $route): AbstractNavigationItem;

    /**
     * @param null|string $fragment
     * @return AbstractNavigationItem
     */
    public function setFragment(?string $fragment): AbstractNavigationItem;

    /**
     * @param string $label
     * @return AbstractNavigationItem
     */
    public function setLabel(string $label): AbstractNavigationItem;

    /**
     * @param null|AbstractNavigation $child
     * @return AbstractNavigationItem
     */
    public function setChild(?AbstractNavigation $child): AbstractNavigationItem;
}
