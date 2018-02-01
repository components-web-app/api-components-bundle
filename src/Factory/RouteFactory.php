<?php

namespace Silverback\ApiComponentBundle\Factory;

use Cocur\Slugify\SlugifyInterface;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Route\Route;

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