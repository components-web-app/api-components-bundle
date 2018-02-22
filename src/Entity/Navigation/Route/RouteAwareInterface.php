<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

interface RouteAwareInterface
{
    /**
     * @param Route $route
     * @return RouteAwareInterface
     */
    public function addRoute(Route $route): RouteAwareInterface;

    /**
     * @param Route $route
     * @return RouteAwareInterface
     */
    public function removeRoute(Route $route): RouteAwareInterface;
}
