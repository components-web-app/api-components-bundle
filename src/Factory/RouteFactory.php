<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Silverback\ApiComponentBundle\Entity\Route\ChildRouteInterface;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Repository\Content\Page\DynamicPageRepository;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class RouteFactory
{
    private $slugify;
    private $manager;
    private $validator;
    private $dynamicPageRepository;

    public function __construct(
        ObjectManager $manager,
        SlugifyInterface $slugify,
        ValidatorInterface $validator,
        DynamicPageRepository $dynamicPageRepository
    ) {
        $this->slugify = $slugify;
        $this->manager = $manager;
        $this->validator = $validator;
        $this->dynamicPageRepository = $dynamicPageRepository;
    }

    /**
     * @param RouteAwareInterface $entity
     * @param int|null $postfix
     * @return Route
     */
    public function createFromRouteAwareEntity(RouteAwareInterface $entity, int $postfix = 0): Route
    {
        $pageRoute = $this->slugify->slugify($entity->getDefaultRoute() ?: '');
        $prefixEntity = $entity;
        if ($entity instanceof DynamicContent && !$entity->getParentRoute()) {
            $dynamicPage = $this->dynamicPageRepository->findOneBy([
                'dynamicPageClass' => \get_class($entity)
            ]);
            if ($dynamicPage) {
                $prefixEntity = $dynamicPage;
            }
        }
        $routePrefix = $this->getRoutePrefix($prefixEntity);
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
        if ($entity instanceof DynamicContent) {
            $route->setDynamicContent($entity);
        }
        if ($entity instanceof StaticPage) {
            $route->setStaticPage($entity);
        }

        $entity->addRoute($route);
        return $route;
    }

    public function createRedirectFromOldRoute(Route $oldRoute, Route $newRoute): Route
    {
        $oldRoute
            ->setName($oldRoute->getName() . '_redirect')
            ->setDynamicContent(null)
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
     * @param ChildRouteInterface $entity
     * @return string
     */
    private function getRoutePrefix(ChildRouteInterface $entity): string
    {
        $parent = $entity->getParentRoute();
        if ($parent) {
            return $parent->getRoute() . '/';
        }
        return '/';
    }
}
