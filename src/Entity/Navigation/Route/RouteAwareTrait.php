<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait RouteAwareTrait
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Navigation\Route\Route", mappedBy="content")
     * @Groups({"layout", "content", "component"})
     * @var ArrayCollection|Route[]
     */
    protected $routes;

    /**
     * @param Route $route
     * @return RouteAwareInterface|RouteAwareTrait
     */
    public function addRoute(Route $route): RouteAwareInterface
    {
        $this->routes->add($route);
        if ($this instanceof RouteAwareInterface) {
            return $this;
        }
    }

    /**
     * @param Route $route
     * @return RouteAwareInterface|RouteAwareTrait
     */
    public function removeRoute(Route $route): RouteAwareInterface
    {
        $this->routes->removeElement($route);
        if ($this instanceof RouteAwareInterface) {
            return $this;
        }
    }
}
