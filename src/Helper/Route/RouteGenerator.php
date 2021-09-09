<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Helper\Route;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
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
        RouteRepository $routeRepository
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
        $entityManager = $this->registry->getManagerForClass($className = \get_class($object));
        if (!$entityManager) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for %s', $className));
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

        if ($parentRoute = $object->getParentRoute()) {
            $path = '/' . ltrim($parentRoute->getPath(), '/') . $path;
        }

        [$name, $path] = $this->resolveConflicts($name, $path);

        $route
            ->setName($name)
            ->setPath($path);
        $object->setRoute($route);

        if ($existingRoute) {
            $existingRoute->setRedirect($route);
            // When we enabled patch endpoint for route, this was required.
            // The existing route is found in uow, perhaps this is why..
            // Future investigation would be nice to know reasoning for this breaking tests and pageData becoming null
            // on the $route and staying on the existingRoute only when patch enabled.
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
            $name = sprintf('%s-%d', $baseName, $conflictCounter);
            $path = sprintf('%s-%d', $basePath, $conflictCounter);
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
