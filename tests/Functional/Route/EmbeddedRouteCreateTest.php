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

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class EmbeddedRouteCreateTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ResourceTrait;
    use SiteTrait;

    private const string PAGES = '/_/pages';
    private const string PAGE_DATAS = '/page_data/page_datas';

    public static function routables(): iterable
    {
        yield 'a page' => [self::PAGES, Page::class];
        yield 'a page data' => [self::PAGE_DATAS, PageData::class];
    }

    public static function collections(): iterable
    {
        yield 'a page' => [self::PAGES];
        yield 'a page data' => [self::PAGE_DATAS];
    }

    #[DataProvider('routables')]
    public function test_a_routable_is_created_with_an_embedded_route(string $collection, string $class): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/about', 'name' => 'about-us']));

        self::assertResponseStatusCodeSame(201);
        $route = self::routeOfCreated($class);
        self::assertSame('/about', $route->getPath());
        self::assertSame('about-us', $route->getName());
        self::assertNotNull($route->getLiveAt());
    }

    #[DataProvider('routables')]
    public function test_the_response_carries_the_embedded_route_as_an_iri(string $collection, string $class): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/about']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::iriOf(self::routeOfCreated($class)), self::jsonNode('route'));
    }

    #[DataProvider('routables')]
    public function test_a_null_live_at_creates_a_draft_route(string $collection, string $class): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/draft', 'name' => 'draft', 'liveAt' => null]));

        self::assertResponseStatusCodeSame(201);
        self::assertNull(self::routeOfCreated($class)->getLiveAt());
    }

    #[DataProvider('routables')]
    public function test_a_taken_path_is_a_violation_on_the_route_path_and_creates_nothing(string $collection, string $class): void
    {
        self::createRouteWithPage('/taken');
        $client = self::adminClient();
        $routes = self::countEntities(Route::class);
        $routables = self::countEntities($class);

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/taken', 'name' => 'another-name']));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', 'This path is already in use.');
        self::assertSame($routes, self::countEntities(Route::class));
        self::assertSame($routables, self::countEntities($class));
    }

    #[DataProvider('collections')]
    public function test_a_taken_name_is_a_violation_on_the_route_name(string $collection): void
    {
        self::createRouteWithPage('/taken');
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/free', 'name' => '/taken']));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.name', 'This route name is already in use.');
    }

    #[DataProvider('collections')]
    public function test_a_blank_path_is_a_violation_on_the_route_path(string $collection): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '', 'name' => 'blank']));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', 'This value should not be blank.');
    }

    #[DataProvider('routables')]
    public function test_a_missing_name_is_derived_from_the_path(string $collection, string $class): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/about/team']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('about-team', self::routeOfCreated($class)->getName());
    }

    #[DataProvider('routables')]
    public function test_a_derived_name_that_is_taken_is_suffixed(string $collection, string $class): void
    {
        $first = self::newPage('first');
        $second = self::newPage('second');
        self::persistAndFlush($first, $second, self::newRoute('/about-team', 'about-team')->setPage($first), self::newRoute('/about-team-x', 'about-team-1')->setPage($second));
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', $collection, self::body($collection, ['path' => '/about/team']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('about-team-2', self::routeOfCreated($class)->getName());
    }

    public function test_an_existing_route_with_no_page_can_be_linked_by_iri(): void
    {
        $route = self::newRoute('/orphan', 'orphan');
        self::persistAndFlush($route);
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::PAGES, self::body(self::PAGES, self::iriOf($route)));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::iriOf($route), self::jsonNode('route'));
    }

    public function test_a_routed_page_can_be_patched_without_its_route_flagging_itself(): void
    {
        $site = self::createRoutedPageWithComponent('/routed');
        $route = $site['route'];
        $pageIri = self::iriOf($site['page']);
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', $pageIri, ['title' => 'A new title', 'route' => self::iriOf($route)]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($route), self::jsonNode('route'));
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    /**
     * @param array<string, mixed>|string $route
     *
     * @return array<string, mixed>
     */
    private static function body(string $collection, array|string $route): array
    {
        if (self::PAGES === $collection) {
            $layout = self::createLayout('layout-' . bin2hex(random_bytes(4)));

            return ['layout' => self::iriOf($layout), 'reference' => 'created-' . bin2hex(random_bytes(4)), 'uiComponent' => 'Primary', 'isTemplate' => false, 'route' => $route];
        }

        $template = self::createPage('template-' . bin2hex(random_bytes(4)), true);

        return ['page' => self::iriOf($template), 'title' => 'Created', 'route' => $route];
    }

    /**
     * @param class-string<AbstractPage> $class
     */
    private static function routeOfCreated(string $class): Route
    {
        $created = self::findByIri(self::jsonNode('@id'));
        self::assertInstanceOf($class, $created);
        $route = $created->getRoute();
        self::assertInstanceOf(Route::class, $route);

        return $route;
    }

    private static function assertViolation(string $propertyPath, string $message): void
    {
        $violations = array_map(static fn (array $violation): array => [$violation['propertyPath'], $violation['message']], self::jsonNode('violations'));

        self::assertContains([$propertyPath, $message], $violations);
    }
}
