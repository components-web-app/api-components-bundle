<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;

interface RouteAwareInterface extends ChildRouteInterface
{
    /**
     * @param Route $route
     * @return static
     */
    public function addRoute(Route $route);
    /**
     * @param Route $route
     * @return static
     */
    public function removeRoute(Route $route);
    public function getDefaultRoute(): string;

    /**
     * @return Collection|Route[]
     */
    public function getRoutes(): Collection;
    public function getDefaultRouteName(): string;

    /**
     * @param bool $regnerateRoute
     * @return static
     */
    public function setRegenerateRoute(bool $regnerateRoute);
    public function getRegenerateRoute(): bool;
}
