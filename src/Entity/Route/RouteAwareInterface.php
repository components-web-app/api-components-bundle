<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

interface RouteAwareInterface
{
    /**
     * @param array $routes
     */
    public function setRoutes(array $routes): void;

    /**
     * @param Route $route
     */
    public function addRoute(Route $route): void;

    /**
     * @param Route $route
     */
    public function removeRoute(Route $route): void;
}
