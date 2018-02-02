<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class RouteAware implements RouteAwareInterface
{
    /**
     * @Groups({"layout", "content", "component"})
     * @var null|Route[]
     */
    protected $routes;

    /**
     * RouteAwareTrait constructor.
     */
    public function __construct()
    {
        $this->routes = new ArrayCollection;
    }

    /**
     * @param Route $route
     * @return RouteAware
     */
    public function addRoute(Route $route): RouteAware
    {
        $this->routes->add($route);
        return $this;
    }

    /**
     * @param Route $route
     * @return RouteAware
     */
    public function removeRoute(Route $route): RouteAware
    {
        $this->routes->removeElement($route);
        return $this;
    }
}
