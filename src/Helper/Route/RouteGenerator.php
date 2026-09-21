<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Route;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteGenerator implements RouteGeneratorInterface
{
    private SlugifyInterface $slugify;
    private ManagerRegistry $registry;
    private TimestampedDataPersister $timestampedDataPersister;
    private RouteRepository $routeRepository;

    public function __construct(
        SlugifyInterface $slugify,
        ManagerRegistry $registry,
        TimestampedDataPersister $timestampedDataPersister,
        RouteRepository $routeRepository,
    ) {
        $this->slugify = $slugify;
        $this->registry = $registry;
        $this->timestampedDataPersister = $timestampedDataPersister;
        $this->routeRepository = $routeRepository;
    }

    public function createRedirect(string $fromPath, Route $targetRoute): Route
    {
        [$name] = $this->resolveConflicts($fromPath, $fromPath);

        $newRedirect = new Route();
        $newRedirect
            ->setName($name)
            ->setPath($fromPath)
            ->setRedirect($targetRoute);
        $this->timestampedDataPersister->persistTimestampedFields($newRedirect, true);

        return $newRedirect;
    }

    public function create(RoutableInterface $object, ?Route $route = null): Route
    {
        $parentPageRoute = $object->getParentPageRoute();
        if (null === $parentPageRoute && (null !== $object->getParentPage() || null !== $object->getParentPageData())) {
            throw new UnroutedParentException('Cannot generate a route for this page because its parent page has no route. Give the parent page a route first, or create this page\'s route explicitly.');
        }

        $entityManager = $this->registry->getManagerForClass($className = $object::class);
        if (!$entityManager) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for %s', $className));
        }
        $uow = $entityManager->getUnitOfWork();
        /** @var RoutableInterface $originalPage */
        $originalPage = $uow->getOriginalEntityData($object);
        $existingRoute = $originalPage['route'] ?? null;

        $isNew = !((bool) $route);
        $route = $route ?? new Route();

        $this->timestampedDataPersister->persistTimestampedFields($route, $isNew);
        $titleSlug = $this->slugify->slugify($object->getTitle());
        $name = $titleSlug;

        $path = '/' . ltrim($titleSlug, '/');

        if ($parentPageRoute) {
            $path = '/' . ltrim($parentPageRoute->getPath(), '/') . $path;
        }

        [$name, $path] = $this->resolveConflicts($name, $path);

        $route
            ->setName($name)
            ->setPath($path);
        $object->setRoute($route);

        if ($existingRoute) {
            $existingRoute->setRedirect($route);
            $route->setPage($existingRoute->getPage());
            $route->setpageData($existingRoute->getPageData());
        }

        return $route;
    }

    private function resolveConflicts(string $name, string $path): array
    {
        $conflicts = $this->routeRepository->findConflicts($name, $path);

        $baseName = $name;
        $basePath = $path;
        $conflictCounter = 0;
        while ($this->conflictExists($name, $path, $conflicts)) {
            ++$conflictCounter;
            $name = \sprintf('%s-%d', $baseName, $conflictCounter);
            $path = \sprintf('%s-%d', $basePath, $conflictCounter);
        }

        return [$name, $path];
    }

    /**
     * @param Route[] $conflicts
     */
    private function conflictExists(string $name, string $path, array $conflicts): bool
    {
        foreach ($conflicts as $conflict) {
            if ($conflict->getPath() === $path) {
                return true;
            }
            if ($conflict->getName() === $name) {
                return true;
            }
        }

        return false;
    }
}
