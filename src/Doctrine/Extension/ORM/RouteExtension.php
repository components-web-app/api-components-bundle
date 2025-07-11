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

namespace Silverback\ApiComponentsBundle\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteExtension implements QueryCollectionExtensionInterface
{
    private ?array $config;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?array $config, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->config = $config;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$this->config || Route::class !== $resourceClass) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];
        foreach ($this->config as $index => $routeConfig) {
            if ($this->resourceAccessChecker->isGranted($resourceClass, $routeConfig['security'])) {
                continue;
            }
            $param = 'path_' . $index;
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->notLike("$alias.path", ':' . $param)
                );
            $queryBuilder->setParameter($param, str_replace('*', '%', $routeConfig['route']));
        }
    }
}
