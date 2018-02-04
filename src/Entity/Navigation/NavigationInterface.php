<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use Doctrine\Common\Collections\Collection;

/**
 * Interface NavigationInterface
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 */
interface NavigationInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return Collection|AbstractNavigationItem[]
     */
    public function getItems();

    /**
     * @param AbstractNavigationItem $navigationItem
     * @return AbstractNavigation
     */
    public function addItem(AbstractNavigationItem $navigationItem): AbstractNavigation;

    /**
     * @param AbstractNavigationItem $navigationItem
     * @return AbstractNavigation
     */
    public function removeItem(AbstractNavigationItem $navigationItem): AbstractNavigation;
}
