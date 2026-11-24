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

final class RoutePathCharactersTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ResourceTrait;
    use SiteTrait;

    private const string MESSAGE = 'A path cannot contain whitespace, "?" or "#".';
    private const string PAGES = '/_/pages';
    private const string PAGE_DATAS = '/page_data/page_datas';

    /** @var list<object> */
    private static array $site = [];

    public static function forbiddenPaths(): iterable
    {
        yield 'a space' => ['/about us'];
        yield 'a tab' => ["/about\tus"];
        yield 'a newline' => ["/about\nus"];
        yield 'a no-break space' => ["/about\u{00A0}us"];
        yield 'a question mark' => ['/about?us'];
        yield 'a hash' => ['/about#us'];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_a_posted_route_whose_path_holds_a_forbidden_character_is_a_violation_on_the_path_and_creates_nothing(string $path): void
    {
        $page = self::createPage('about');
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'POST', '/_/routes', ['path' => $path, 'name' => 'about', 'page' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['path', self::MESSAGE]], self::violations());
        self::assertSame($routes, self::countEntities(Route::class));
    }

    public function test_a_posted_route_with_an_ordinary_path_is_created_with_that_path(): void
    {
        $page = self::createPage('about');

        self::requestJsonLd(self::adminClient(), 'POST', '/_/routes', ['path' => '/about-us/2027_day.one~x', 'name' => 'about', 'page' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('path', '/about-us/2027_day.one~x');
    }

    #[DataProvider('forbiddenPaths')]
    public function test_patching_a_route_path_to_hold_a_forbidden_character_is_a_violation_on_the_path_and_changes_nothing(string $path): void
    {
        $route = self::createRouteWithPage('/about');

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($route), ['path' => $path]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['path', self::MESSAGE]], self::violations());
        self::assertInstanceOf(Route::class, self::findByIri('/_/routes//about'));
    }

    #[DataProvider('forbiddenPaths')]
    public function test_a_posted_redirect_whose_path_holds_a_forbidden_character_is_a_violation_on_the_path(string $path): void
    {
        $target = self::createRouteWithPage('/about');

        self::requestJsonLd(self::adminClient(), 'POST', '/_/routes', ['path' => $path, 'name' => 'old-about', 'redirect' => self::iriOf($target)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['path', self::MESSAGE]], self::violations());
    }

    #[DataProvider('forbiddenPaths')]
    public function test_a_page_created_with_an_embedded_route_whose_path_holds_a_forbidden_character_is_a_violation_on_the_route_path_and_creates_nothing(string $path): void
    {
        $layout = self::createLayout('layout-' . bin2hex(random_bytes(4)));
        $pages = self::countEntities(Page::class);

        self::requestJsonLd(self::adminClient(), 'POST', self::PAGES, ['layout' => self::iriOf($layout), 'reference' => 'about', 'uiComponent' => 'Primary', 'isTemplate' => false, 'route' => ['path' => $path, 'name' => 'about']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['route.path', self::MESSAGE]], self::violations());
        self::assertSame($pages, self::countEntities(Page::class));
    }

    #[DataProvider('forbiddenPaths')]
    public function test_page_data_created_with_an_embedded_route_whose_path_holds_a_forbidden_character_is_a_violation_on_the_route_path_and_creates_nothing(string $path): void
    {
        $template = self::createPage('template', true);
        $pageData = self::countEntities(PageData::class);

        self::requestJsonLd(self::adminClient(), 'POST', self::PAGE_DATAS, ['page' => self::iriOf($template), 'title' => 'About', 'route' => ['path' => $path, 'name' => 'about']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['route.path', self::MESSAGE]], self::violations());
        self::assertSame($pageData, self::countEntities(PageData::class));
    }

    public function test_a_cascade_that_would_rewrite_a_stored_descendant_path_holding_a_forbidden_character_is_a_violation_on_the_path_and_changes_nothing(): void
    {
        $parent = self::routedPage('/conference', null);
        self::routedPage('/conference/day one', $parent);
        self::persistSite();
        $routes = self::countEntities(Route::class);

        self::requestJsonLd(self::adminClient(), 'PATCH', '/_/routes//conference', ['path' => '/summit', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['path', 'The path of the descendant route "/conference/day one" would become "/summit/day one". ' . self::MESSAGE]], self::violations());
        self::assertSame($routes, self::countEntities(Route::class));
        self::assertSame('/conference', self::routeOf($parent)->getPath());
    }

    public function test_a_move_that_would_carry_a_stored_last_segment_holding_a_forbidden_character_is_a_violation_on_the_parent_page_and_moves_nothing(): void
    {
        $blog = self::routedPage('/blog', null);
        $news = self::routedPage('/news', null);
        $post = self::routedPage('/blog/my post', $blog);
        self::persistSite();

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($post), ['parentPage' => self::iriOf($news)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['parentPage', 'Moving this page would change the route "/blog/my post" to "/news/my post". ' . self::MESSAGE]], self::violations());
        self::assertSame('/blog/my post', self::routeOf($post)->getPath());
    }

    public function test_a_stored_route_whose_path_holds_a_forbidden_character_can_be_written_without_changing_its_path(): void
    {
        $route = self::createRouteWithPage('/about us');

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($route), ['liveAt' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/about us');
    }

    public function test_a_page_whose_stored_route_path_holds_a_forbidden_character_can_be_written(): void
    {
        $page = self::routedPage('/about us', null);
        self::persistSite();

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($page), ['title' => 'About us']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('title', 'About us');
    }

    public function test_a_stored_route_whose_path_holds_a_forbidden_character_can_be_renamed_to_an_ordinary_path(): void
    {
        $route = self::createRouteWithPage('/about us');

        self::requestJsonLd(self::adminClient(), 'PATCH', self::iriOf($route), ['path' => '/about-us']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/about-us');
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function routedPage(string $path, ?Page $parent): Page
    {
        $page = static::newPage('page ' . bin2hex(random_bytes(4)));
        $page->layout = static::newLayout('layout-' . bin2hex(random_bytes(4)));
        $page->uiComponent = 'CwaPagePrimary';
        $page->setParentPage($parent);
        array_push(self::$site, $page->layout, $page, static::newRoute($path)->setPage($page));

        return $page;
    }

    private static function persistSite(): void
    {
        $site = self::$site;
        self::$site = [];
        self::persistAndFlush(...$site);
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
