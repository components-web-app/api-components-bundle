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

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteGenerator implements RouteGeneratorInterface
{
    private SlugifyInterface $slugify;
    private ManagerRegistry $registry;
    private TimestampedDataPersister $timestampedDataPersister;

    public function __construct(SlugifyInterface $slugify, ManagerRegistry $registry, TimestampedDataPersister $timestampedDataPersister)
    {
        $this->slugify = $slugify;
        $this->registry = $registry;
        $this->timestampedDataPersister = $timestampedDataPersister;
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
        $path = '/' . ltrim($titleSlug, '/');

        if ($parentRoute = $object->getParentRoute()) {
            $path = '/' . ltrim($parentRoute->getPath(), '/') . $path;
        }

        $route
            ->setName($titleSlug)
            ->setPath($path);
        $object->setRoute($route);

        if ($existingRoute) {
            $existingRoute->setRedirect($route);
        }

        return $route;
    }
}
