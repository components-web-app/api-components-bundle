<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Collection;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CollectionTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class CollectionTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use CollectionTrait;
    use JsonTrait;

    public function test_a_collection_component_can_be_created(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/collections', '{"resourceIri": "/page_data/page_datas", "perPage": 20, "defaultQueryParameters": {"search": "something", "orderBy": "anotherThing"}}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('collection.schema.json');
    }

    #[DataProvider('invalidResourceIris')]
    public function test_a_collection_component_cannot_be_created_with_an_invalid_resource_iri(?string $resourceIri): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/collections', ['resourceIri' => $resourceIri]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidResourceIris(): iterable
    {
        yield 'null' => [null];
        yield 'an unknown path' => ['/invalid'];
        yield 'the root' => ['/'];
    }

    public function test_a_collection_component_can_be_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $collection = self::createCollection();

        self::requestJsonLd($client, 'DELETE', self::iriOf($collection));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_the_collection_is_purged_from_the_cache_when_a_dummy_component_is_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $components = self::createDummyComponents(2);
        $collection = self::createCollection();

        self::requestJsonLd($client, 'DELETE', self::iriOf($components[0]));

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurged(self::iriOf($collection));
    }

    public function test_a_site_config_write_never_looks_up_collection_components_so_it_touches_no_other_table(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $collection = self::createCollection('/_/site_config_parameters');
        $parameter = (new SiteConfigParameter())->setKey('siteName')->setValue('CWA');
        self::persistAndFlush($parameter);
        $table = static::getEntityManager()->getClassMetadata(Collection::class)->getTableName();
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'PUT', self::iriOf($parameter), ['key' => 'siteName', 'value' => 'Renamed']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], QueryCountingMiddleware::queriesOnTable($table));
        self::assertCacheTagNotPurged(self::iriOf($collection));
        self::assertCacheTagPurged('cwa-html');
    }

    public function test_a_collection_is_fetched_with_the_default_pagination_enabled(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyComponents(50);
        $collection = self::createCollection();

        self::requestJsonLd($client, 'GET', self::iriOf($collection));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('collection.member', 30);
        self::assertJsonNodeSame('collection.totalItems', 50);
        self::assertJsonNodeSame('collection.@id', '/component/dummy_components');
        self::assertJsonNodeSame('collection.@type', 'Collection');
    }

    public function test_a_collection_component_is_created_with_per_page_configured(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyComponents(50);

        self::requestJsonLd($client, 'POST', '/component/collections', '{"resourceIri": "/component/dummy_components", "perPage": 3}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeCount('collection.member', 3);
    }

    #[DataProvider('paginationParameters')]
    public function test_the_pagination_parameter_is_configured(int $total, string $postfix): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyResourcesWithPagination(120);

        self::requestJsonLd($client, 'GET', '/dummy_resource_with_paginations' . $postfix);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', $total);
    }

    #[DataProvider('paginationParameters')]
    public function test_component_pagination_can_be_configured(int $total, string $postfix): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyResourcesWithPagination(120);
        $collection = self::createCollection('/dummy_resource_with_paginations');

        self::requestJsonLd($client, 'GET', self::iriOf($collection) . $postfix);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('collection.member', $total);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function paginationParameters(): iterable
    {
        yield 'the default' => [10, ''];
        yield 'perPage=20' => [20, '?perPage=20'];
        yield 'perPage over the maximum' => [40, '?perPage=110'];
        yield 'pagination disabled' => [120, '?pagination=false'];
        yield 'perPage=0' => [0, '?perPage=0'];
    }

    #[DataProvider('directFilters')]
    public function test_a_resource_can_be_searched_directly_without_a_collection(int $total, string $postfix): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyResourcesWithFilters(80);

        self::requestJsonLd($client, 'GET', '/dummy_resource_with_filters' . $postfix);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', $total);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function directFilters(): iterable
    {
        yield 'reference=1' => [17, '?reference=1'];
        yield 'pagination disabled and reference=1' => [17, '?pagination=false&reference=1'];
        yield 'an empty reference' => [30, '?reference='];
        yield 'pagination disabled and an empty reference' => [80, '?pagination=false&reference='];
        yield 'perPage=20 and an empty reference' => [20, '?perPage=20&reference='];
        yield 'reference=10' => [1, '?reference=10'];
    }

    #[DataProvider('defaultQueryParameterFilters')]
    public function test_a_collection_has_default_query_string_parameters_and_filters(int $total, string $postfix): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createDummyResourcesWithFilters(80);
        $collection = self::createCollection('/dummy_resource_with_filters', ['perPage' => 40, 'reference' => '1']);

        self::requestJsonLd($client, 'GET', self::iriOf($collection) . $postfix);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('collection.member', $total);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function defaultQueryParameterFilters(): iterable
    {
        yield 'the defaults' => [17, ''];
        yield 'pagination disabled' => [17, '?pagination=false'];
        yield 'an empty reference' => [40, '?reference='];
        yield 'pagination disabled and an empty reference' => [80, '?pagination=false&reference='];
        yield 'perPage=20 and an empty reference' => [20, '?perPage=20&reference='];
        yield 'reference=10' => [1, '?reference=10'];
    }

    public function test_a_default_query_parameter_reaches_a_query_parameter_filter_of_the_collected_resource(): void
    {
        $client = static::createClient();
        self::persistAndFlush(new Route()->setPath('/about')->setName('about'), new Route()->setPath('/about-us')->setName('about-us'), new Route()->setPath('/contact')->setName('contact'));
        $collection = self::createCollection('/_/routes', ['search' => 'about']);

        self::requestJsonLd($client, 'GET', self::iriOf($collection));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('collection.totalItems', 2);
    }

    public function test_a_query_parameter_in_the_request_overrides_the_collections_default_for_a_query_parameter_filter(): void
    {
        $client = static::createClient();
        self::persistAndFlush(new Route()->setPath('/about')->setName('about'), new Route()->setPath('/about-us')->setName('about-us'), new Route()->setPath('/contact')->setName('contact'));
        $collection = self::createCollection('/_/routes', ['search' => 'about']);

        self::requestJsonLd($client, 'GET', self::iriOf($collection) . '?search=contact');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('collection.totalItems', 1);
        self::assertJsonNodeSame('collection.member[0].path', '/contact');
    }
}
