<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\ArrayCollection;

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
     * @return ArrayCollection
     */
    public function getRoutes(): ArrayCollection;
}
