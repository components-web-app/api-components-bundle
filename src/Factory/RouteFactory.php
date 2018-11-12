<?php

namespace Silverback\ApiComponentBundle\Factory;

use Cocur\Slugify\SlugifyInterface;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Repository\RouteRepository;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class RouteFactory
{
    private $slugify;
    private $repository;

    public function __construct(
        SlugifyInterface $slugify,
        RouteRepository $repository
    ) {
        $this->slugify = $slugify;
        $this->repository = $repository;
    }

    /**
     * @param RouteAwareInterface $entity
     * @param int|null $postfix
     * @return Route
     */
    public function createFromRouteAwareEntity(RouteAwareInterface $entity, int $postfix = 0): Route
    {
        $pageRoute = $this->slugify->slugify($entity->getDefaultRoute() ?: '');
        $routePrefix = $this->getRoutePrefix($entity);
        $fullRoute = $routePrefix . $pageRoute;
        if ($postfix > 0) {
            $fullRoute .= '-' . $postfix;
        }
        $existing = $this->repository->find($fullRoute);
        if ($existing) {
            return $this->createFromRouteAwareEntity($entity, $postfix + 1);
        }
        $converter = new CamelCaseToSnakeCaseNameConverter();
        $generatedName = $converter->normalize(str_replace(' ', '', $entity->getDefaultRouteName()));
        $name = $generatedName;
        $counter = 0;
        while ($this->repository->findOneBy(['name' => $name])) {
            $counter++;
            $name = sprintf('%s-%s', $generatedName, $counter);
        }
        $route = new Route();
        $route->setName($name)->setRoute($fullRoute);
        if ($entity instanceof AbstractContent) {
            $route->setContent($entity);
        }
        $entity->addRoute($route);
        return $route;
    }
    /**
     * @param RouteAwareInterface $entity
     * @return string
     */
    private function getRoutePrefix(RouteAwareInterface $entity): string
    {
        $parent = $entity->getParentRoute();
        if ($parent) {
            return $parent->getRoute() . '/';
        }
        return '/';
    }
}
