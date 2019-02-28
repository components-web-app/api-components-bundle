<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;

trait RouteAwareTrait
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="content", cascade={"persist"})
     * @Groups({"default", "route_read"})
     * @var Collection|Route[]
     */
    protected $routes;

    /**
     * @var bool
     * @Groups({"default_write"})
     */
    private $regenerateRoute = false;

    /**
     * @param Route $route
     * @return RouteAwareInterface|RouteAwareTrait
     */
    public function addRoute(Route $route)
    {
        if ($this instanceof AbstractContent) {
            $route->setContent($this);
        }
        $this->routes->add($route);
        return $this;
    }

    /**
     * @param Route $route
     * @return RouteAwareInterface|RouteAwareTrait
     */
    public function removeRoute(Route $route)
    {
        $this->routes->removeElement($route);
        return $this;
    }

    /**
     * @return Collection|Route[]
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    public function setRegenerateRoute(bool $regenerateRoute)
    {
        $this->regenerateRoute = $regenerateRoute;
        return $this;
    }

    public function getRegenerateRoute(): bool
    {
        return $this->regenerateRoute;
    }
}
