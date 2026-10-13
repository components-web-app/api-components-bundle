<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache\Warm;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class PublicRoutePaths
{
    public function __construct(private ManagerRegistry $registry, private RouteExtension $routeExtension)
    {
    }

    /**
     * @return list<string>
     */
    public function find(?int $limit): array
    {
        $manager = $this->registry->getManagerForClass(Route::class);
        if (!$manager instanceof EntityManagerInterface) {
            return [];
        }

        $queryBuilder = $manager->createQueryBuilder()
            ->select('route.path')
            ->addSelect('LENGTH(route.path) AS HIDDEN path_length')
            ->from(Route::class, 'route')
            ->orderBy('path_length')
            ->addOrderBy('route.path')
            ->setMaxResults($limit);
        $this->routeExtension->applyToCollection($queryBuilder, new QueryNameGenerator(), Route::class);

        return $queryBuilder->getQuery()->getSingleColumnResult();
    }
}
