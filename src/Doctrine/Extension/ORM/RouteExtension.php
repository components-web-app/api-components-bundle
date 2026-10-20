<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\Utility\PublicationDate;
use Silverback\ApiComponentsBundle\Utility\RouteSecurityPattern;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteExtension implements QueryCollectionExtensionInterface
{
    private ?array $config;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?array $config, ResourceAccessCheckerInterface $resourceAccessChecker, private readonly RouteAncestorGateResolver $routeAncestorGateResolver, private readonly string $publicationPermission = "is_granted('ROLE_ADMIN')")
    {
        $this->config = $config;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Route::class !== $resourceClass) {
            return;
        }
        $alias = $queryBuilder->getRootAliases()[0];

        if (!$this->resourceAccessChecker->isGranted($resourceClass, $this->publicationPermission)) {
            PublicationDate::andWhereActive($queryBuilder, $alias, 'liveAt');
            $this->routeAncestorGateResolver->andWhereNotGated($queryBuilder, $queryNameGenerator, $alias);
        }

        $patterns = [];
        foreach ($this->config ?? [] as $routeConfig) {
            if (!$this->resourceAccessChecker->isGranted($resourceClass, $routeConfig['security'])) {
                $patterns[] = $routeConfig['route'];
            }
        }
        if (!$patterns) {
            return;
        }

        $manager = $queryBuilder->getEntityManager();
        $restrictedRouteIds = $this->fetchRouteIdsMatching($manager, $patterns);
        if (!$restrictedRouteIds) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('cwa_restricted_route_ids');
        $queryBuilder
            ->andWhere($queryBuilder->expr()->notIn(\sprintf('%s.%s', $alias, $manager->getClassMetadata(Route::class)->getSingleIdentifierFieldName()), ':' . $parameter))
            ->setParameter($parameter, $restrictedRouteIds);
    }

    /**
     * @param non-empty-list<string> $patterns
     *
     * @return list<string>
     */
    private function fetchRouteIdsMatching(EntityManagerInterface $manager, array $patterns): array
    {
        $metadata = $manager->getClassMetadata(Route::class);
        $connection = $manager->getConnection();
        $platform = $connection->getDatabasePlatform();
        $path = $metadata->getColumnName('path');

        $conditions = [];
        $parameters = [];
        foreach ($patterns as $index => $pattern) {
            $conditions[] = RouteSecurityPattern::sqlCondition($platform, $path, ':pattern_' . $index);
            $parameters['pattern_' . $index] = RouteSecurityPattern::sqlParameter($platform, $pattern);
        }

        $sql = \sprintf('SELECT %s FROM %s WHERE %s', $metadata->getSingleIdentifierColumnName(), $metadata->getTableName(), implode(' OR ', $conditions));

        return array_map(static fn ($id): string => (string) $id, $connection->executeQuery($sql, $parameters)->fetchFirstColumn());
    }
}
