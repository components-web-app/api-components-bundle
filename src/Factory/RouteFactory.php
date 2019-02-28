<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class RouteFactory
{
    private $slugify;
    private $manager;
    private $validator;

    public function __construct(
        ObjectManager $manager,
        SlugifyInterface $slugify,
        ValidatorInterface $validator
    ) {
        $this->slugify = $slugify;
        $this->manager = $manager;
        $this->validator = $validator;
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

        $repository = $this->manager->getRepository(Route::class);
        $existing = $repository->findOneBy(['route' => $fullRoute]);
        if ($existing) {
            return $this->createFromRouteAwareEntity($entity, $postfix + 1);
        }

        $converter = new CamelCaseToSnakeCaseNameConverter();
        $generatedName = $converter->normalize(str_replace(' ', '', $entity->getDefaultRouteName()));
        $name = $generatedName;
        $counter = 0;
        while ($repository->findOneBy(['name' => $name])) {
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

    public function createRedirectFromOldRoute(Route $oldRoute, Route $newRoute): Route
    {
        $oldRoute
            ->setName($oldRoute->getName() . '_redirect')
            ->setContent(null)
            ->setRedirect($newRoute)
        ;
        return $oldRoute;
    }

    /**
     * @param RouteAwareInterface $entity
     * @param EntityManagerInterface|null $entityManager
     * @return null|ArrayCollection|Route[]
     */
    public function createPageRoute(RouteAwareInterface $entity, ?EntityManagerInterface $entityManager = null): ?ArrayCollection
    {
        $em = $entityManager ?: $this->manager;

        $entityRoutes = $entity->getRoutes();
        if (($routeCount = $entityRoutes->count()) === 0 || $entity->getRegenerateRoute()) {
            $newRoutes = new ArrayCollection();
            $entity->setRegenerateRoute(false);

            $newRoute = $this->createFromRouteAwareEntity($entity);
            $this->validator->validate($newRoute);
            $em->persist($newRoute);

            if ($routeCount > 0 && ($defaultRoute = $entityRoutes->first())) {
                $this->createRedirectFromOldRoute($defaultRoute, $newRoute);
                $newRoutes->add($defaultRoute);
                $entity->removeRoute($defaultRoute);
            }

            $newRoutes->add($newRoute);
            return $newRoutes;
        }
        return null;
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
