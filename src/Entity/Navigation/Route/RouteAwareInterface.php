<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

interface RouteAwareInterface
{
    /**
     * @param Route $route
     * @return RouteAware
     */
    public function addRoute(Route $route): RouteAware;

    /**
     * @param Route $route
     * @return RouteAware
     */
    public function removeRoute(Route $route): RouteAware;
}
