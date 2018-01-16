<?php

namespace Silverback\ApiComponentBundle\Factory;

use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Entity\Route;
use Cocur\Slugify\SlugifyInterface;

class RouteFactory {
    /**
     * @var SlugifyInterface
     */
    private $slugify;

    public function __construct(
        SlugifyInterface $slugify
    )
    {
        $this->slugify = $slugify;
    }

    public function create(Page $page) {
        $pageRoute = $this->slugify->slugify($page->getTitle());
        $routePrefix = '/';
        $parent = $page->getParent();
        if ($parent) {
            $parentRoute = $parent->getRoutes()->first();
            if (!$parentRoute) {
                $parentRoute = $this->create($parent);
            }
            $routePrefix = $parentRoute->getRoute() . '/';
        }
        $route = new Route(
            $routePrefix . $pageRoute,
            $page
        );
        $page->addRoute($route);
        return $route;
    }
}