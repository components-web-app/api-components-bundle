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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class PageMoveRouteTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use MercureTrait;
    use ResourceTrait;
    use SiteTrait;

    /** @var list<object> */
    private static array $site = [];

    public function test_a_page_moved_under_a_routed_parent_takes_the_new_parent_path_and_the_response_carries_its_new_route(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $post = self::routedPage('/blog/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//news/my-post');
        self::assertSame('/news/my-post', self::routeOf($post)->getPath());
        self::assertRouteRedirectsTo('/blog/my-post', '/news/my-post');
    }

    public function test_prefixed_page_data_moved_to_a_new_parent_keeps_its_prefix_under_the_new_parent_path(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $announcement = new AnnouncementPageData();
        $announcement->page = static::newPage('announcement template', true);
        $announcement->setParentPage($blog);
        self::persistSite($announcement->page, $announcement, static::newRoute('/blog/announcements/launch')->setPageData($announcement));

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($announcement), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//news/announcements/launch');
        self::assertRouteRedirectsTo('/blog/announcements/launch', '/news/announcements/launch');
    }

    public function test_page_data_moved_to_a_new_parent_page_data_takes_its_path(): void
    {
        $blog = self::routedPageData('/blog', null);
        $news = self::routedPageData('/news', null);
        $post = self::routedPageData('/blog/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPageData' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//news/my-post');
        self::assertRouteRedirectsTo('/blog/my-post', '/news/my-post');
    }

    public function test_the_descendants_of_a_moved_page_are_rewritten_and_every_old_path_redirects_to_its_new_route(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $post = self::routedPage('/blog/post', $blog);
        $comments = self::routedPage('/blog/post/comments', $post);
        $unrouted = self::page('unrouted', $comments);
        self::routedPage('/blog/post/comments/first', $unrouted);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/blog/post', '/news/post');
        self::assertRouteRedirectsTo('/blog/post/comments', '/news/post/comments');
        self::assertRouteRedirectsTo('/blog/post/comments/first', '/news/post/comments/first');
        self::assertRouteDoesNotRedirect('/news/post/comments/first');
    }

    public function test_a_moved_page_with_no_route_rewrites_its_routed_descendants(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $section = self::page('section', $blog);
        $child = self::routedPage('/blog/child', $section);
        self::routedPage('/blog/child/grandchild', $child);
        self::persistSite();
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($section), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/blog/child', '/news/child');
        self::assertRouteRedirectsTo('/blog/child/grandchild', '/news/child/grandchild');
        self::assertSame($routes + 2, self::countEntities(Route::class));
    }

    public function test_a_page_moved_to_no_parent_takes_its_path_to_the_root(): void
    {
        $blog = self::routedParent('/blog');
        $post = self::routedPage('/blog/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//my-post');
        self::assertRouteRedirectsTo('/blog/my-post', '/my-post');
    }

    public function test_a_route_outside_its_old_parent_path_keeps_only_its_last_segment_under_the_new_parent(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $post = self::routedPage('/legacy/archive/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/legacy/archive/my-post', '/news/my-post');
    }

    public function test_a_move_that_leaves_the_path_unchanged_writes_no_route_and_no_redirect(): void
    {
        $blog = self::routedParent('/blog');
        $section = self::page('section', $blog);
        $post = self::routedPage('/blog/my-post', $blog);
        self::persistSite();
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($section)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//blog/my-post');
        self::assertRouteDoesNotRedirect('/blog/my-post');
        self::assertSame($routes, self::countEntities(Route::class));
    }

    public function test_a_move_to_a_taken_path_is_a_violation_on_the_parent_page_and_moves_nothing(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $post = self::routedPage('/blog/my-post', $blog);
        $taken = static::newPage('taken');
        self::persistSite($taken, static::newRoute('/news/my-post')->setPage($taken));
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['parentPage', 'Moving this page would change the route "/blog/my-post" to "/news/my-post". This path is already in use.']],
            self::violations(),
        );
        self::assertSame($routes, self::countEntities(Route::class));
        self::assertSame('/blog/my-post', self::routeOf($post)->getPath());
        $reloaded = self::findByIri(self::iriOf($post));
        self::assertInstanceOf(Page::class, $reloaded);
        self::assertSame(self::iriOf($blog), self::iriOf($reloaded->getParentPage() ?? self::fail('The page lost its parent.')));
    }

    public function test_a_move_that_would_take_a_descendant_path_is_a_violation_on_the_parent_page_data_naming_the_descendant(): void
    {
        $blog = self::routedPageData('/blog', null);
        $news = self::routedPageData('/news', null);
        $post = self::routedPageData('/blog/post', $blog);
        self::routedPageData('/blog/post/comments', $post);
        $taken = static::newPage('taken');
        self::persistSite($taken, static::newRoute('/news/post/comments')->setPage($taken));
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPageData' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['parentPageData', 'Moving this page would change the route "/blog/post/comments" to "/news/post/comments". This path is already in use.']],
            self::violations(),
        );
        self::assertSame($routes, self::countEntities(Route::class));
        self::assertSame('/blog/post', self::routeOf($post)->getPath());
    }

    public function test_a_move_to_a_path_that_is_too_long_is_a_violation_on_the_parent_page(): void
    {
        $blog = self::routedParent('/blog');
        $long = self::routedParent('/' . str_repeat('a', 250));
        $post = self::routedPage('/blog/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($long)]);

        self::assertResponseStatusCodeSame(422);
        $violations = self::violations();
        self::assertCount(1, $violations);
        self::assertSame('parentPage', $violations[0][0]);
        self::assertStringStartsWith(\sprintf('Moving this page would change the route "/blog/my-post" to "/%s/my-post". This value is too long.', str_repeat('a', 250)), $violations[0][1]);
        self::assertSame('/blog/my-post', self::routeOf($post)->getPath());
    }

    public function test_a_move_purges_the_route_collection_and_the_iris_of_both_paths_and_publishes_the_new_route(): void
    {
        $blog = self::routedParent('/blog');
        $news = self::routedParent('/news');
        $post = self::routedPage('/blog/my-post', $blog);

        self::persistSite();
        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged('/_/routes');
        self::assertCacheTagPurged('/_/routes//news/my-post');
        self::assertCacheTagPurged('/_/routes//blog/my-post');
        self::assertMercureUpdatePublishedFor('/_/routes//news/my-post');
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function routedParent(string $path): Page
    {
        return self::routedPage($path, null);
    }

    private static function routedPage(string $path, ?Page $parent): Page
    {
        $page = self::newEditablePage('page ' . $path, $parent);
        array_push(self::$site, $page->layout, $page, static::newRoute($path)->setPage($page));

        return $page;
    }

    private static function page(string $reference, Page $parent): Page
    {
        $page = self::newEditablePage($reference, $parent);
        array_push(self::$site, $page->layout, $page);

        return $page;
    }

    private static function persistSite(object ...$entities): void
    {
        $site = [...self::$site, ...$entities];
        self::$site = [];
        self::persistAndFlush(...$site);
    }

    private static function newEditablePage(string $reference, ?Page $parent): Page
    {
        $page = static::newPage($reference . ' ' . bin2hex(random_bytes(4)));
        $page->layout = static::newLayout('layout-' . bin2hex(random_bytes(4)));
        $page->uiComponent = 'CwaPagePrimary';
        $page->setParentPage($parent);

        return $page;
    }

    private static function routedPageData(string $path, ?PageData $parent): PageData
    {
        $pageData = new PageData();
        $pageData->page = static::newPage('template ' . bin2hex(random_bytes(4)), true);
        $pageData->setParentPageData($parent);
        array_push(self::$site, $pageData->page, $pageData, static::newRoute($path)->setPageData($pageData));

        return $pageData;
    }

    private static function routeOf(AbstractPage $page): Route
    {
        $reloaded = self::findByIri(self::iriOf($page));
        self::assertInstanceOf(AbstractPage::class, $reloaded);

        return $reloaded->getRoute() ?? self::fail('The page has no route.');
    }

    /**
     * @return list<array{string, string}>
     */
    private static function violations(): array
    {
        return array_map(static fn (array $violation): array => [$violation['propertyPath'], $violation['message']], self::jsonNode('violations'));
    }
}
