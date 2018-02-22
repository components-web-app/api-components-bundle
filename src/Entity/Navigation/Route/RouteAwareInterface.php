<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

interface RouteAwareInterface
{
    /**
     * @param Route $route
     * @return RouteAwareTrait
     */
    public function addRoute(Route $route): RouteAwareTrait;

    /**
     * @param Route $route
     * @return RouteAwareTrait
     */
    public function removeRoute(Route $route): RouteAwareTrait;
}
