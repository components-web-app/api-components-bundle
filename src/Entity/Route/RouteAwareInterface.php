<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;

interface RouteAwareInterface
{
    /**
     * @param Route $route
     * @return RouteAwareTrait|RouteAwareInterface
     */
    public function addRoute(Route $route);

    /**
     * @param Route $route
     * @return RouteAwareTrait|RouteAwareInterface
     */
    public function removeRoute(Route $route);

    /**
     * @return string
     */
    public function getDefaultRoute(): string;

    /**
     * @return Collection
     */
    public function getRoutes(): Collection;

    /**
     * @return string
     */
    public function getDefaultRouteName(): string;
}
