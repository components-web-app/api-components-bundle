<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

use Silverback\ApiComponentBundle\Entity\Component\SortableInterface;
use Silverback\ApiComponentBundle\Entity\Route\Route;

interface NavItemInterface extends SortableInterface
{
    public function getId(): ?int;
    public function setId(int $id): void;
    public function getNav(): AbstractNav;
    public function setNav(AbstractNav $nav): void;
    public function getRoute(): ?Route;
    public function setRoute(?Route $route): void;
    public function getFragment(): ?string;
    public function setFragment(?string $fragment): void;
    public function getLabel(): string;
    public function setLabel(string $label): void;
    public function getChild(): ?AbstractNav;
    public function setChild(?AbstractNav $child): void;
}
