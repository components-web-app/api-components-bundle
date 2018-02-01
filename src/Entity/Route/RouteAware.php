<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Page;

abstract class RouteAware implements RouteAwareInterface
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="page", cascade={"persist", "remove"})
     * @var null|Route[]
     */
    protected $routes;

    /**
     * RouteAwareTrait constructor.
     */
    public function __construct()
    {
        $this->routes = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /**
     * @param array $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = new ArrayCollection();
        foreach ($routes as $route)
        {
            $this->addRoute($route);
        }
    }

    /**
     * @param Route $route
     */
    public function addRoute(Route $route): void
    {
        if ($this instanceof Page) {
            $route->setPage($this);
        }
        $this->routes->add($route);
    }

    /**
     * @param Route $route
     */
    public function removeRoute(Route $route): void
    {
        $this->routes->removeElement($route);
    }
}
