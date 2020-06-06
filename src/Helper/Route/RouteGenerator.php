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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteGenerator
{
    private SlugifyInterface $slugify;
    private ManagerRegistry $registry;

    public function __construct(SlugifyInterface $slugify, ManagerRegistry $registry)
    {
        $this->slugify = $slugify;
        $this->registry = $registry;
    }

    public function createFromPage(AbstractPage $page, ?Route $route = null): Route
    {
        $entityManager = $this->registry->getManagerForClass($className = \get_class($page));
        if (!$entityManager) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for %s', $className));
        }
        $uow = $entityManager->getUnitOfWork();
        /** @var AbstractPage $originalPage */
        $originalPage = $uow->getOriginalEntityData($page);
        $existingRoute = $originalPage['route'];

        $route = $route ?? new Route();

        $path = $this->slugify->slugify($page->getTitle());

        $route
            ->setName(sprintf('generated-%s', $path))
            ->setPath('/' . ltrim($path, '/'));
        $page->setRoute($route);

        if ($existingRoute) {
            $existingRoute->setRedirect($route);
        }

        return $route;
    }
}
