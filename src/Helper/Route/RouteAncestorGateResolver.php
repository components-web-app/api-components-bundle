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

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class RouteAncestorGateResolver
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function andWhereNotGated(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $routeAlias): void
    {
        $gatedRouteIds = $this->getGatedRouteIds();
        if (!$gatedRouteIds) {
            return;
        }

        $parameter = $queryNameGenerator->generateParameterName('cwa_gated_route_ids');
        $identifier = $this->registry->getManagerForClass(Route::class)->getClassMetadata(Route::class)->getSingleIdentifierFieldName();

        $queryBuilder
            ->andWhere($queryBuilder->expr()->notIn("$routeAlias.$identifier", ':' . $parameter))
            ->setParameter($parameter, $gatedRouteIds);
    }

    /**
     * @return list<string>
     */
    public function getGatedRouteIds(): array
    {
        $manager = $this->registry->getManagerForClass(Route::class);
        if (!$manager instanceof EntityManagerInterface) {
            return [];
        }

        $result = $manager->getConnection()->executeQuery(
            $this->buildSql($manager),
            ['now' => new \DateTimeImmutable()],
            ['now' => Types::DATETIME_IMMUTABLE]
        );

        return array_values(array_map(static fn ($id) => (string) $id, $result->fetchFirstColumn()));
    }

    public function buildSql(EntityManagerInterface $manager): string
    {
        $routeMetadata = $manager->getClassMetadata(Route::class);
        $pageMetadata = $manager->getClassMetadata(Page::class);
        $pageDataMetadata = $manager->getClassMetadata(AbstractPageData::class);

        $routeTable = $routeMetadata->getTableName();
        $routeId = $routeMetadata->getSingleIdentifierColumnName();
        $routeLiveAt = $routeMetadata->getColumnName('liveAt');

        $pageTable = $pageMetadata->getTableName();
        $pageId = $pageMetadata->getSingleIdentifierColumnName();
        $pageRoute = $this->joinColumn($pageMetadata, 'route');
        $pageParentPage = $this->joinColumn($pageMetadata, 'parentPage');
        $pageParentPageData = $this->joinColumn($pageMetadata, 'parentPageData');

        $pageDataTable = $pageDataMetadata->getTableName();
        $pageDataId = $pageDataMetadata->getSingleIdentifierColumnName();
        $pageDataRoute = $this->joinColumn($pageDataMetadata, 'route');
        $pageDataParentPage = $this->joinColumn($pageDataMetadata, 'parentPage');
        $pageDataParentPageData = $this->joinColumn($pageDataMetadata, 'parentPageData');

        return <<<SQL
            WITH RECURSIVE cwa_route_ancestor (route_id, parent_page_id, parent_page_data_id) AS (
                SELECT r.$routeId,
                       COALESCE(p.$pageParentPage, d.$pageDataParentPage),
                       COALESCE(p.$pageParentPageData, d.$pageDataParentPageData)
                FROM $routeTable r
                LEFT JOIN $pageTable p ON p.$pageRoute = r.$routeId
                LEFT JOIN $pageDataTable d ON d.$pageDataRoute = r.$routeId
                UNION
                SELECT a.route_id,
                       COALESCE(p.$pageParentPage, d.$pageDataParentPage),
                       COALESCE(p.$pageParentPageData, d.$pageDataParentPageData)
                FROM cwa_route_ancestor a
                LEFT JOIN $pageTable p ON p.$pageId = a.parent_page_id
                LEFT JOIN $pageDataTable d ON d.$pageDataId = a.parent_page_data_id
                WHERE a.parent_page_id IS NOT NULL OR a.parent_page_data_id IS NOT NULL
            )
            SELECT DISTINCT a.route_id AS route_id
            FROM cwa_route_ancestor a
            LEFT JOIN $pageTable p ON p.$pageId = a.parent_page_id
            LEFT JOIN $pageDataTable d ON d.$pageDataId = a.parent_page_data_id
            INNER JOIN $routeTable ar ON ar.$routeId = COALESCE(p.$pageRoute, d.$pageDataRoute)
            WHERE ar.$routeLiveAt IS NULL OR ar.$routeLiveAt > :now
            SQL;
    }

    private function joinColumn(ClassMetadata $metadata, string $fieldName): string
    {
        return $metadata->getSingleAssociationJoinColumnName($fieldName);
    }
}
