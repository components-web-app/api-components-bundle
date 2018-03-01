<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Route;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RouteFactory extends AbstractFactory
{
    /**
     * @var SlugifyInterface
     */
    private $slugify;

    public function __construct(
        ObjectManager $manager,
        ValidatorInterface $validator,
        SlugifyInterface $slugify
    )
    {
        $this->slugify = $slugify;
        parent::__construct($manager, $validator);
    }

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Route
    {
        $component = new Route();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    protected static function defaultOps(): array
    {
        return [
            'route' => null,
            'content' => null,
            'redirect' => null
        ];
    }

    /**
     * @param RouteAwareInterface $entity
     * @return Route
     */
    public function createFromRouteAwareEntity(RouteAwareInterface $entity): Route
    {
        $pageRoute = $this->slugify->slugify($entity->getDefaultRoute());
        $routePrefix = $this->getRoutePrefix($entity);
        return $this->create(
            [
                'route' => $routePrefix . $pageRoute,
                'content' => $entity
            ]
        );
    }

    /**
     * @param RouteAwareInterface $entity
     * @return string
     */
    private function getRoutePrefix(RouteAwareInterface $entity): string
    {
        $parent = method_exists($entity, 'getParent') ? $entity->getParent() : null;
        if ($parent && $parent instanceof RouteAwareInterface) {
            $parentRoute = $this->getParentRoute($parent);
            return $parentRoute->getRoute() . '/';
        }
        return '/';
    }

    /**
     * @param RouteAwareInterface $parent
     * @return mixed|Route
     */
    private function getParentRoute(RouteAwareInterface $parent)
    {
        $parentRoute = $parent->getRoutes()->first();
        if (!$parentRoute) {
            $parentRoute = $this->createFromRouteAwareEntity($parent);
        }
        return $parentRoute;
    }
}
