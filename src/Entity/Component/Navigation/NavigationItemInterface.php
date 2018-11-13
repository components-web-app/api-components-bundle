<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation;

use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Silverback\ApiComponentBundle\Entity\Route\Route;

/**
 * Interface NavigationItemInterface
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 */
interface NavigationItemInterface extends ComponentInterface
{
    /**
     * @return null|Route
     */
    public function getRoute(): ?Route;

    /**
     * @param null|Route $route
     * @return AbstractNavigationItem
     */
    public function setRoute(?Route $route): AbstractNavigationItem;

    /**
     * @return null|string
     */
    public function getFragment(): ?string;

    /**
     * @param null|string $fragment
     * @return AbstractNavigationItem
     */
    public function setFragment(?string $fragment): AbstractNavigationItem;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     * @return AbstractNavigationItem
     */
    public function setLabel(string $label): AbstractNavigationItem;
}
