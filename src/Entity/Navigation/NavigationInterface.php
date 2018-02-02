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
     * @return Collection|NavigationItemInterface[]
     */
    public function getItems();

    /**
     * @param NavigationItemInterface $navigationItem
     * @return AbstractNavigation
     */
    public function addItem(NavigationItemInterface $navigationItem): AbstractNavigation;

    /**
     * @param NavigationItemInterface $navigationItem
     * @return AbstractNavigation
     */
    public function removeItem(NavigationItemInterface $navigationItem): AbstractNavigation;
}
