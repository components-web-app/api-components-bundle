<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

use Silverback\ApiComponentBundle\Entity\Route;

interface NavItemInterface
{
    public function getId(): ?int;
    public function setId(int $id): void;
    public function getNav(): Nav;
    public function setNav(Nav $nav): void;
    public function getRoute(): ?Route;
    public function setRoute(?Route $route): void;
    public function getFragment(): ?string;
    public function setFragment(?string $fragment): void;
    public function getLabel(): string;
    public function setLabel(string $label): void;
    public function getSortOrder(): int;
    public function setSortOrder(int $sortOrder): void;
    public function getChild(): ?Nav;
    public function setChild(?Nav $child): void;
}
