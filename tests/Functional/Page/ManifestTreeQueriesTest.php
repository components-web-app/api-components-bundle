<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;

final class ManifestTreeQueriesTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use ImagePageSiteTrait;

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function pageSizes(): iterable
    {
        yield 'two components owning one each' => [2, 1];
        yield 'five components owning three each' => [5, 3];
    }

    #[DataProvider('pageSizes')]
    public function test_a_manifest_loads_its_groups_positions_and_components_in_two_queries_per_owner_level(int $images, int $nestedImages): void
    {
        $client = static::createClient();
        self::createImagePageWithNestedGroups('/gallery', $images, $nestedImages);
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//gallery');

        self::assertResponseStatusCodeSame(200);
        $treeQueries = self::treeQueries();
        self::assertCount(8, $treeQueries, implode("\n", $treeQueries));
    }

    /**
     * @return list<string>
     */
    private static function treeQueries(): array
    {
        $manager = static::getEntityManager();
        $tables = array_map(static fn (string $class): string => $manager->getClassMetadata($class)->getTableName(), [ComponentGroup::class, ComponentPosition::class, AbstractComponent::class]);
        $onTree = [];
        foreach ($tables as $table) {
            foreach (QueryCountingMiddleware::queriesOnTable($table) as $query) {
                $onTree[$query] = true;
            }
        }

        return array_values(array_filter(QueryCountingMiddleware::queries(), static fn (string $query): bool => isset($onTree[$query])));
    }
}
