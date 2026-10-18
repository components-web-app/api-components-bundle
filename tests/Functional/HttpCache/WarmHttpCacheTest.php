<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\HttpCache;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\KernelHttpClient;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class WarmHttpCacheTest extends FunctionalTestCase
{
    use ConsoleTrait;
    use SiteTrait;

    private const string COMMAND = 'silverback:api-components:warm-http-cache';

    public function test_every_request_for_a_page_data_route_with_a_dynamic_position_succeeds_the_position_carrying_its_depths_path(): void
    {
        self::createPageDataWithPublishedComponentInPageDataPropertyPosition('/warm-me');

        $tester = self::runConsoleCommand(self::COMMAND, ['--concurrency' => '1']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertWarmRequested('/_/routes//warm-me');
        self::assertWarmRequested('/_/resource_manifest//warm-me');
        self::assertWarmRequestedMatchingWithPathHeader('/\/_\/component_positions\//', '/warm-me');
        self::assertWarmRequestedMatchingWithPathHeader('/\/component\/dummy_publishable_components\//', '/warm-me');
        $requests = KernelHttpClient::getRequests();
        self::assertNotEmpty($requests, 'The HTTP cache warm made no request');
        foreach ($requests as $request) {
            self::assertSame(200, $request['status'], \sprintf('The HTTP cache warm request for "%s" returned %d. It requested: %s', $request['url'], $request['status'], self::describeWarmRequests()));
        }
        self::assertStringContainsString('Warmed 1 route with', $tester->getDisplay());
        self::assertStringContainsString('0 failed', $tester->getDisplay());
    }

    public function test_a_route_gated_by_its_own_or_an_ancestors_go_live_date_is_not_requested_and_a_redirect_gets_only_its_route_lookup(): void
    {
        self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', null);
        self::createRouteWithPageAndRedirect('/old', '/new');

        $tester = self::runConsoleCommand(self::COMMAND);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertWarmNotRequestedContaining('conference');
        self::assertWarmRequested('/_/routes//old');
        self::assertWarmNotRequestedContaining('/_/resource_manifest//old');
    }

    public function test_a_route_only_a_logged_in_user_may_read_is_not_requested_and_no_request_carries_credentials(): void
    {
        self::createComponentInRoute('/user-area/secret');
        self::createPageDataWithRoute('/public');

        $tester = self::runConsoleCommand(self::COMMAND);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertWarmRequested('/_/routes//public');
        self::assertWarmNotRequestedContaining('user-area');
        $requests = KernelHttpClient::getRequests();
        self::assertNotEmpty($requests, 'The HTTP cache warm made no request');
        foreach ($requests as $request) {
            self::assertArrayNotHasKey('cookie', $request['headers'], $request['url']);
            self::assertArrayNotHasKey('authorization', $request['headers'], $request['url']);
        }
    }

    public function test_an_error_response_is_reported_and_never_fails_the_command(): void
    {
        self::createComponentInRoute('/public');
        KernelHttpClient::respondWith('/\/_\/pages\//', 500);

        $tester = self::runConsoleCommand(self::COMMAND);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('1 failed', $tester->getDisplay());
    }

    private static function createPageDataWithPublishedComponentInPageDataPropertyPosition(string $path): void
    {
        $group = self::newComponentGroup('test');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'publishableComponent';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = self::newPage('test page', true);
        $page->addComponentGroup($group);
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime());
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $published;
        $pageData->page = $page;
        $route = self::newRoute($path)->setPageData($pageData);
        self::persistAndFlush($group, $position, $page, $published, $pageData, $route);
    }

    private static function createPageNestedWithinRoute(string $childPath, string $parentPath): void
    {
        $parentPage = self::newPage('parent page', true);
        $parentRoute = self::newRoute($parentPath)->setPage($parentPage);
        $childPage = self::newPage('child page', true);
        $childPage->setParentPage($parentPage);
        $childRoute = self::newRoute($childPath)->setPage($childPage);
        self::persistAndFlush($parentPage, $parentRoute, $childPage, $childRoute);
    }

    private static function createRouteWithPageAndRedirect(string $path, string $redirectPath): void
    {
        $redirect = self::newRoute($redirectPath);
        $page = self::newPage('route-page');
        $route = self::newRoute($path)->setRedirect($redirect)->setPage($page);
        self::persistAndFlush($redirect, $page, $route);
    }

    private static function assertWarmRequested(string $path): void
    {
        foreach (KernelHttpClient::getRequests() as $request) {
            if (parse_url($request['url'], \PHP_URL_PATH) === $path) {
                return;
            }
        }

        self::fail(\sprintf('The HTTP cache warm did not request "%s". It requested: %s', $path, self::describeWarmRequests()));
    }

    private static function assertWarmRequestedMatchingWithPathHeader(string $pattern, string $pathHeader): void
    {
        foreach (KernelHttpClient::getRequests() as $request) {
            if (preg_match($pattern, $request['url']) && ($request['headers']['path'] ?? null) === $pathHeader) {
                return;
            }
        }

        self::fail(\sprintf('The HTTP cache warm did not request a URL matching "%s" with the path header "%s". It requested: %s', $pattern, $pathHeader, self::describeWarmRequests()));
    }

    private static function assertWarmNotRequestedContaining(string $text): void
    {
        foreach (KernelHttpClient::getRequests() as $request) {
            self::assertStringNotContainsString($text, $request['url'], 'The HTTP cache warm requested it');
        }
    }

    private static function describeWarmRequests(): string
    {
        return implode(', ', array_map(static fn (array $request): string => \sprintf('%d %s (path: %s)', $request['status'], $request['url'], $request['headers']['path'] ?? '-'), KernelHttpClient::getRequests()));
    }
}
