<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Route;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Symfony\Component\Validator\Constraints\Count;

final class RoutableFilterTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use RouteSiteTrait;

    private const string FUTURE = '2999-01-01T00:00:00+00:00';

    public function test_the_pages_parameter_returns_the_route_of_each_listed_page(): void
    {
        $client = static::createClient();
        $pages = self::createPagesWithRoutes(['/one', '/two', '/three']);

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pages', [$pages['/one'], $pages['/three']]));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 2);
        self::assertMemberPaths(['/one', '/three']);
    }

    public function test_the_pages_parameter_accepts_a_single_iri_without_brackets(): void
    {
        $client = static::createClient();
        $pages = self::createPagesWithRoutes(['/one', '/two']);

        self::requestJsonLd($client, 'GET', '/_/routes?pages=' . rawurlencode(self::iriOf($pages['/two'])));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/two']);
    }

    public function test_the_page_data_parameter_returns_the_route_of_each_listed_page_data(): void
    {
        $client = static::createClient();
        $first = self::createRouteWithPageDataAndRedirect('/first', '/first-target');
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pageData', [$first['pageData']]));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/first']);
    }

    public function test_the_page_data_parameter_accepts_a_single_iri_without_brackets(): void
    {
        $client = static::createClient();
        $first = self::createRouteWithPageDataAndRedirect('/first', '/first-target');

        self::requestJsonLd($client, 'GET', '/_/routes?pageData=' . rawurlencode(self::iriOf($first['pageData'])));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/first']);
    }

    public function test_a_page_with_no_route_has_no_row(): void
    {
        $client = static::createClient();
        $pages = self::createPagesWithRoutes(['/one']);
        $unrouted = self::createPage('unrouted');

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pages', [$pages['/one'], $unrouted]));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/one']);
    }

    public function test_a_value_that_is_not_an_iri_matches_no_route(): void
    {
        $client = static::createClient();
        self::createPagesWithRoutes(['/one']);

        self::requestJsonLd($client, 'GET', '/_/routes?pages[]=not-an-iri');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 0);
    }

    public function test_fifty_page_iris_return_all_fifty_routes_in_one_page_of_up_to_a_hundred(): void
    {
        $client = static::createClient();
        $paths = array_map(static fn (int $i): string => '/page-' . $i, range(1, 50));
        $pages = self::createPagesWithRoutes($paths);

        self::requestJsonLd($client, 'GET', '/_/routes?perPage=100&' . self::query('pages', array_values($pages)));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 50);
        self::assertMemberPaths($paths);
    }

    #[DataProvider('provideFilterParameters')]
    public function test_a_hundred_iris_are_accepted(string $parameter): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::unknownIriQuery($parameter, 100));

        self::assertResponseStatusCodeSame(200);
    }

    #[DataProvider('provideFilterParameters')]
    public function test_more_than_a_hundred_iris_is_a_violation_on_the_parameter(string $parameter): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::unknownIriQuery($parameter, 101));

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $parameter);
        self::assertJsonNodeSame('violations[0].code', Count::TOO_MANY_ERROR);
        self::assertJsonNodeNotExists('violations[1]');
    }

    public static function provideFilterParameters(): iterable
    {
        yield 'pages' => ['pages'];
        yield 'pageData' => ['pageData'];
    }

    public function test_an_admin_gets_gated_routes_with_their_go_live_dates(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pages = self::createGatedPages();

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pages', array_values($pages)));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/draft', '/scheduled', '/conference/programme']);
        $draft = self::memberIndex('/draft');
        self::assertJsonNodeNotExists(\sprintf('member[%d].liveAt', $draft));
        self::assertJsonNodeNotExists(\sprintf('member[%d]._metadata.effectiveLiveAt', $draft));
        $scheduled = self::memberIndex('/scheduled');
        self::assertJsonNodeMatches(\sprintf('member[%d].liveAt', $scheduled), '/^2999-01-01/');
        self::assertJsonNodeMatches(\sprintf('member[%d]._metadata.effectiveLiveAt', $scheduled), '/^2999-01-01/');
        $heldBack = self::memberIndex('/conference/programme');
        self::assertJsonNodeMatches(\sprintf('member[%d].liveAt', $heldBack), '/^\\d{4}-/');
        self::assertJsonNodeMatches(\sprintf('member[%d]._metadata.effectiveLiveAt', $heldBack), '/^2999-01-01/');
    }

    public function test_an_anonymous_caller_gets_no_gated_route_and_no_go_live_date(): void
    {
        $client = static::createClient();
        $pages = self::createGatedPages();
        $live = self::createPagesWithRoutes(['/live']);

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pages', [...array_values($pages), $live['/live']]));

        self::assertResponseStatusCodeSame(200);
        self::assertMemberPaths(['/live']);
        self::assertJsonNodeNotExists('member[0].liveAt');
        self::assertJsonNodeNotExists('member[0]._metadata.effectiveLiveAt');
    }

    public function test_a_filtered_collection_is_cached_as_the_unfiltered_one_and_tagged_with_the_collection(): void
    {
        $client = static::createClient();
        $pages = self::createPagesWithRoutes(['/one']);
        self::requestJsonLd($client, 'GET', '/_/routes');
        $unfiltered = $client->getResponse()->getHeaders(false)['cache-control'];

        self::requestJsonLd($client, 'GET', '/_/routes?' . self::query('pages', [$pages['/one']]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame($unfiltered, $client->getResponse()->getHeaders(false)['cache-control']);
        self::assertResponseHasCacheTag('/_/routes');
        self::assertResponseHasCacheTag('/_/routes//one');
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, Page>
     */
    private static function createPagesWithRoutes(array $paths): array
    {
        $pages = [];
        $entities = [];
        foreach ($paths as $path) {
            $pages[$path] = $page = static::newPage('page' . $path);
            $entities[] = $page;
            $entities[] = static::newRoute($path)->setPage($page);
        }
        static::persistAndFlush(...$entities);

        return $pages;
    }

    /**
     * @return array{draft: Page, scheduled: Page, heldBack: Page}
     */
    private static function createGatedPages(): array
    {
        $pages = self::createPagesWithRoutes(['/draft', '/scheduled']);
        $nested = self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/draft', null);
        self::setRouteLiveAt('/scheduled', new \DateTimeImmutable(self::FUTURE));
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        return ['draft' => $pages['/draft'], 'scheduled' => $pages['/scheduled'], 'heldBack' => $nested['page']];
    }

    /**
     * @param list<Page|PageData> $resources
     */
    private static function query(string $parameter, array $resources): string
    {
        return implode('&', array_map(static fn (object $resource): string => $parameter . '[]=' . rawurlencode(self::iriOf($resource)), $resources));
    }

    private static function unknownIriQuery(string $parameter, int $count): string
    {
        $prefix = 'pages' === $parameter ? '/_/pages/' : '/page_data/page_datas/';

        return implode('&', array_map(static fn (): string => $parameter . '[]=' . rawurlencode($prefix . '00000000-0000-4000-8000-' . bin2hex(random_bytes(6))), range(1, $count)));
    }

    /**
     * @param list<string> $paths
     */
    private static function assertMemberPaths(array $paths): void
    {
        $actual = array_column(self::jsonNode('member'), 'path');
        sort($actual);
        sort($paths);
        self::assertSame($paths, $actual);
    }

    private static function memberIndex(string $path): int
    {
        $index = array_search($path, array_column(self::jsonNode('member'), 'path'), true);
        self::assertIsInt($index);

        return $index;
    }
}
