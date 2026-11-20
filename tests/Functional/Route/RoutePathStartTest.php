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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AnnouncementPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;

final class RoutePathStartTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ResourceTrait;
    use RouteSiteTrait;

    private const string ANNOUNCEMENTS = '/page_data/announcement_page_datas';
    private const string UPDATES = '/page_data/announcement_update_page_datas';
    private const string OUTSIDE_MESSAGE = 'This path must start with "%s" and continue after it.';

    public function test_the_api_docs_advertise_the_route_prefix_on_the_supported_class_of_a_prefixed_page_data_class(): void
    {
        self::requestJsonLd(static::createClient(), 'GET', '/docs');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('/announcements', self::supportedClass('AnnouncementPageData')['routePrefix'] ?? null);
        self::assertSame('/updates', self::supportedClass('AnnouncementUpdatePageData')['routePrefix'] ?? null);
    }

    public function test_the_api_docs_carry_no_route_prefix_for_a_page_data_class_without_one(): void
    {
        self::requestJsonLd(static::createClient(), 'GET', '/docs');

        self::assertArrayNotHasKey('routePrefix', self::supportedClass('PageData'));
    }

    public function test_prefixed_page_data_is_created_with_an_embedded_route_under_its_prefix(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, self::pageDataBody(['path' => '/announcements/launch']));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('/announcements/launch', self::routeOfCreated()->getPath());
    }

    public function test_prefixed_page_data_may_have_several_segments_after_its_prefix(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, self::pageDataBody(['path' => '/announcements/2027/launch']));

        self::assertResponseStatusCodeSame(201);
    }

    public function test_an_embedded_route_outside_the_prefix_is_a_violation_on_the_route_path_and_creates_nothing(): void
    {
        $client = self::adminClient();
        $body = self::pageDataBody(['path' => '/launch']);
        $routes = self::countEntities(Route::class);
        $pageData = self::countEntities(AnnouncementPageData::class);

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/announcements/'));
        self::assertSame($routes, self::countEntities(Route::class));
        self::assertSame($pageData, self::countEntities(AnnouncementPageData::class));
    }

    public function test_an_embedded_route_that_is_only_the_prefix_is_a_violation_on_the_route_path(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, self::pageDataBody(['path' => '/announcements/']));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/announcements/'));
    }

    public function test_a_route_posted_for_prefixed_page_data_outside_its_prefix_is_a_violation_on_the_path(): void
    {
        $pageData = self::newAnnouncement();
        self::persistAndFlush($pageData->page, $pageData);
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/launch', 'name' => 'launch', 'pageData' => self::iriOf($pageData)]);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('path', \sprintf(self::OUTSIDE_MESSAGE, '/announcements/'));
    }

    public function test_a_route_posted_for_prefixed_page_data_under_its_prefix_is_created(): void
    {
        $pageData = self::newAnnouncement();
        self::persistAndFlush($pageData->page, $pageData);
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/announcements/launch', 'name' => 'launch', 'pageData' => self::iriOf($pageData)]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_prefixed_page_data_under_routed_page_data_must_start_with_the_parent_path_then_its_own_prefix(): void
    {
        $parent = self::newAnnouncement();
        self::persistAndFlush($parent->page, $parent, self::newRoute('/announcements/launch')->setPageData($parent));
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::UPDATES, self::pageDataBody(['path' => '/updates/first'], ['parentPageData' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/announcements/launch/updates/'));

        self::requestJsonLd($client, 'POST', self::UPDATES, self::pageDataBody(['path' => '/announcements/launch/updates/first'], ['parentPageData' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_nested_page_created_with_an_embedded_route_outside_its_parent_path_is_a_violation_on_the_route_path(): void
    {
        $parent = self::createRouteWithPage('/conference')->getPage();
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/pages', self::pageBody(['path' => '/programme'], ['parentPage' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/conference/'));
    }

    public function test_a_nested_page_is_created_with_an_embedded_route_under_its_parent_path(): void
    {
        $parent = self::createRouteWithPage('/conference')->getPage();
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/pages', self::pageBody(['path' => '/conference/programme'], ['parentPage' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_page_with_no_routed_ancestor_may_have_any_path(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/pages', self::pageBody(['path' => '/anything/at/all']));

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_route_posted_for_a_nested_page_outside_its_parent_path_is_a_violation_on_the_path(): void
    {
        $parent = static::newPage('parent');
        $child = static::newPage('child')->setParentPage($parent);
        $grandchild = static::newPage('grandchild')->setParentPage($child);
        self::persistAndFlush($parent, self::newRoute('/conference')->setPage($parent), $child, self::newRoute('/conference/programme')->setPage($child), $grandchild);
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/day-one', 'name' => 'day-one', 'page' => self::iriOf($grandchild)]);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('path', \sprintf(self::OUTSIDE_MESSAGE, '/conference/programme/'));
    }

    public function test_an_unrouted_parent_is_skipped_for_the_nearest_routed_ancestor(): void
    {
        $grandparent = static::newPage('routed grandparent');
        $parent = static::newPage('unrouted parent')->setParentPage($grandparent);
        self::persistAndFlush($grandparent, self::newRoute('/conference')->setPage($grandparent), $parent);
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, self::pageDataBody(['path' => '/announcements/launch'], ['parentPage' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/conference/announcements/'));

        self::requestJsonLd($client, 'POST', self::ANNOUNCEMENTS, self::pageDataBody(['path' => '/conference/announcements/launch'], ['parentPage' => self::iriOf($parent)]));

        self::assertResponseStatusCodeSame(201);
    }

    public function test_patching_a_route_path_outside_its_parent_path_is_a_violation_on_the_path(): void
    {
        $site = self::createPageNestedWithinRoute('/conference/programme', '/conference');
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['path' => '/programme']);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('path', \sprintf(self::OUTSIDE_MESSAGE, '/conference/'));
    }

    public function test_patching_a_route_path_within_its_parent_path_is_accepted(): void
    {
        $site = self::createPageNestedWithinRoute('/conference/programme', '/conference');
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['path' => '/conference/schedule']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/conference/schedule');
    }

    public function test_patching_an_existing_route_outside_its_parent_path_without_changing_its_path_is_accepted(): void
    {
        $site = self::createPageNestedWithinRoute('/programme', '/conference');
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['liveAt' => null]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_patching_a_route_path_of_prefixed_page_data_outside_its_prefix_is_a_violation_on_the_path(): void
    {
        $pageData = self::newAnnouncement();
        $route = self::newRoute('/announcements/launch')->setPageData($pageData);
        self::persistAndFlush($pageData->page, $pageData, $route);
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($route), ['path' => '/launch']);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('path', \sprintf(self::OUTSIDE_MESSAGE, '/announcements/'));
    }

    public function test_linking_an_existing_route_outside_the_parent_path_is_a_violation_on_the_route_path(): void
    {
        $page = self::createPageWhoseParentIsRouted('/conference', self::newRoute('/elsewhere', 'elsewhere'));
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), ['route' => '/_/routes//elsewhere']);

        self::assertResponseStatusCodeSame(422);
        self::assertViolation('route.path', \sprintf(self::OUTSIDE_MESSAGE, '/conference/'));
    }

    public function test_linking_an_existing_route_within_the_parent_path_is_accepted(): void
    {
        $page = self::createPageWhoseParentIsRouted('/conference', self::newRoute('/conference/programme', 'programme'));
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), ['route' => '/_/routes//conference/programme']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//conference/programme');
    }

    public function test_a_pure_redirect_may_have_any_path(): void
    {
        $site = self::createPageNestedWithinRoute('/conference/programme', '/conference');
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/programme', 'name' => 'programme-redirect', 'redirect' => self::iriOf($site['route'])]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_cascade_keeps_a_prefixed_descendant_under_its_parent_path_and_prefix(): void
    {
        $parent = static::newPage('news');
        $parentRoute = self::newRoute('/news')->setPage($parent);
        $pageData = self::newAnnouncement();
        $pageData->setParentPage($parent);
        self::persistAndFlush($parent, $parentRoute, $pageData->page, $pageData, self::newRoute('/news/announcements/launch')->setPageData($pageData));
        $client = self::adminClient();

        self::requestJsonLd($client, 'PATCH', self::iriOf($parentRoute), ['path' => '/press', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/news/announcements/launch', '/press/announcements/launch');

        self::requestJsonLd($client, 'PATCH', '/_/routes//press/announcements/launch', ['path' => '/press/announcements/launch-day']);

        self::assertResponseStatusCodeSame(200);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function newAnnouncement(): AnnouncementPageData
    {
        $pageData = new AnnouncementPageData();
        $pageData->page = static::newPage('announcement template ' . bin2hex(random_bytes(4)), true);

        return $pageData;
    }

    private static function createPageWhoseParentIsRouted(string $parentPath, Route $route, bool $linked = false): Page
    {
        $parent = static::newPage('routed parent');
        $layout = static::newLayout('layout-' . bin2hex(random_bytes(4)));
        $page = static::newPage('child');
        $page->layout = $layout;
        $page->uiComponent = 'CwaPagePrimary';
        $page->setParentPage($parent);
        if ($linked) {
            $route->setPage($page);
        }
        self::persistAndFlush($parent, self::newRoute($parentPath)->setPage($parent), $layout, $page, $route);

        return $page;
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function pageDataBody(array $route, array $extra = []): array
    {
        $template = self::createPage('template-' . bin2hex(random_bytes(4)), true);

        return ['page' => self::iriOf($template), 'title' => 'Created', 'route' => $route] + $extra;
    }

    /**
     * @param array<string, mixed> $route
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function pageBody(array $route, array $extra = []): array
    {
        $layout = self::createLayout('layout-' . bin2hex(random_bytes(4)));

        return ['layout' => self::iriOf($layout), 'reference' => 'created-' . bin2hex(random_bytes(4)), 'uiComponent' => 'Primary', 'isTemplate' => false, 'route' => $route] + $extra;
    }

    private static function routeOfCreated(): Route
    {
        $created = self::findByIri(self::jsonNode('@id'));
        self::assertInstanceOf(AbstractPage::class, $created);
        $route = $created->getRoute();
        self::assertInstanceOf(Route::class, $route);

        return $route;
    }

    /**
     * @return array<string, mixed>
     */
    private static function supportedClass(string $title): array
    {
        $json = self::lastJson();
        foreach ($json['supportedClass'] ?? $json['hydra:supportedClass'] ?? [] as $supportedClass) {
            if (($supportedClass['title'] ?? null) === $title) {
                return $supportedClass;
            }
        }

        self::fail(\sprintf('supportedClass "%s" not found in the API docs.', $title));
    }

    private static function assertViolation(string $propertyPath, string $message): void
    {
        $violations = array_map(static fn (array $violation): array => [$violation['propertyPath'], $violation['message']], self::jsonNode('violations'));

        self::assertContains([$propertyPath, $message], $violations);
    }
}
