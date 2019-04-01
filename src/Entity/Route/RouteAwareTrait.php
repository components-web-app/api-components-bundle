<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Symfony\Component\Serializer\Annotation\Groups;

trait RouteAwareTrait
{
    use ChildRouteTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="staticPage", cascade={"persist"})
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
        if ($this instanceof DynamicContent) {
            $route->setDynamicContent($this);
        }
        if ($this instanceof StaticPage) {
            $route->setStaticPage($this);
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
