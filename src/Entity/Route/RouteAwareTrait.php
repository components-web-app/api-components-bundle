<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;

trait RouteAwareTrait
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="content", cascade={"persist"})
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
}
