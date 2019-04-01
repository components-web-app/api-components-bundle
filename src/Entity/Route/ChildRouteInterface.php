<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

interface ChildRouteInterface
{
    public function isNested(): bool;
    public function setNested(bool $nested);
    public function setParentRoute(?Route $parentRoute);
    public function getParentRoute(): ?Route;
}
