<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\Collection;

interface RouteAwareInterface
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
    public function getRoutes(): Collection;
    public function getDefaultRouteName(): string;
    public function getParentRoute(): ?Route;

    /**
     * @param bool $regnerateRoute
     * @return static
     */
    public function setRegenerateRoute(bool $regnerateRoute);
    public function getRegenerateRoute(): bool;
}
