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

use ApiPlatform\Validator\Exception\ValidationException;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Utility\RouteName;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteGenerator implements RouteGeneratorInterface
{
    public const TITLE_REQUIRED_MESSAGE = 'A title is needed to generate a route.';

    private SlugifyInterface $slugify;
    private ManagerRegistry $registry;
    private TimestampedDataPersister $timestampedDataPersister;
    private RouteRepository $routeRepository;
    private ValidatorInterface $validator;

    public function __construct(
        SlugifyInterface $slugify,
        ManagerRegistry $registry,
        TimestampedDataPersister $timestampedDataPersister,
        RouteRepository $routeRepository,
        ValidatorInterface $validator,
    ) {
        $this->slugify = $slugify;
        $this->registry = $registry;
        $this->timestampedDataPersister = $timestampedDataPersister;
        $this->routeRepository = $routeRepository;
        $this->validator = $validator;
    }

    public function createRedirect(string $fromPath, Route $targetRoute): Route
    {
        $name = $fromPath;
        for ($suffix = 1; $this->routeRepository->count(['name' => $name]) > 0; ++$suffix) {
            $name = RouteName::withSuffix($fromPath, $suffix);
        }

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

        $slug = $this->slugifyTitle($object);
        if (null === $slug) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(self::TITLE_REQUIRED_MESSAGE, self::TITLE_REQUIRED_MESSAGE, [], $object, 'title', $object->getTitle())]));
        }

        $entityManager = $this->registry->getManagerForClass($className = $object::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for %s', $className));
        }
        $uow = $entityManager->getUnitOfWork();
        $originalPage = $uow->getOriginalEntityData($object);
        $existingRoute = $originalPage['route'] ?? null;

        $isNew = !((bool) $route);
        $route = $route ?? new Route();

        $this->timestampedDataPersister->persistTimestampedFields($route, $isNew);

        [$name, $path] = $this->resolveConflicts($slug, $this->pathFor($object, $slug));

        $route
            ->setName($name)
            ->setPath($path);

        $violations = $this->validator->validateProperty($route, 'path');
        $violations->addAll($this->validator->validateProperty($route, 'name'));
        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $object->setRoute($route);

        if ($existingRoute) {
            $existingRoute->setRedirect($route);
            $route->setPage($existingRoute->getPage());
            $route->setpageData($existingRoute->getPageData());
        }

        return $route;
    }

    public function generatePath(RoutableInterface $object): ?string
    {
        $slug = $this->slugifyTitle($object);

        return null === $slug ? null : $this->pathFor($object, $slug);
    }

    private function pathFor(RoutableInterface $object, string $slug): string
    {
        $path = '/' . ltrim($slug, '/');
        $parentPageRoute = $object->getParentPageRoute();
        if ($parentPageRoute) {
            $path = '/' . ltrim($parentPageRoute->getPath(), '/') . $path;
        }

        return $path;
    }

    private function slugifyTitle(RoutableInterface $object): ?string
    {
        $slug = $this->slugify->slugify((string) $object->getTitle());

        return '' === $slug ? null : $slug;
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
