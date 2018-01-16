<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

use Doctrine\Common\Collections\Collection;

interface NavInterface {
    public function getItems(): Collection;
    public function setItems(array $items): void;
    public function addItem(NavItemInterface $item): void;
    public function removeItem(NavItemInterface $item): void;
    public function createNavItem(): NavItemInterface;
}
