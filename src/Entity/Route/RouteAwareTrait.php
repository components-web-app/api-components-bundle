<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait RouteAwareTrait
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="content")
     * @Groups({"layout", "content", "component"})
     * @var Collection|Route[]
     */
    protected $routes;

    /**
     * @param Route $route
     * @return RouteAwareInterface|RouteAwareTrait
     */
    public function addRoute(Route $route)
    {
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
}
