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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

trait RouteSiteTrait
{
    use SiteTrait;

    /**
     * @return array{route: Route, page: Page, redirect: Route}
     */
    protected static function createRouteWithPageAndRedirect(string $path, string $redirectPath): array
    {
        $redirect = static::newRoute($redirectPath);
        $page = static::newPage('route-page');
        $route = static::newRoute($path)->setRedirect($redirect)->setPage($page);
        static::persistAndFlush($redirect, $page, $route);

        return ['route' => $route, 'page' => $page, 'redirect' => $redirect];
    }

    /**
     * @return array{route: Route, pageData: PageData, redirect: Route}
     */
    protected static function createRouteWithPageDataAndRedirect(string $path, string $redirectPath): array
    {
        $redirect = static::newRoute($redirectPath);
        $page = static::newPage('template-page', true);
        $pageData = new PageData();
        $pageData->setTitle('Test PageData');
        $pageData->page = $page;
        $route = static::newRoute($path)->setRedirect($redirect)->setPageData($pageData);
        static::persistAndFlush($redirect, $page, $pageData, $route);

        return ['route' => $route, 'pageData' => $pageData, 'redirect' => $redirect];
    }

    /**
     * @return array{route: Route, middle: Route, final: Route, page: Page}
     */
    protected static function createRouteRedirectingThroughAMiddleRouteTo(string $path, string $finalPath): array
    {
        $final = static::newRoute($finalPath);
        $middle = static::newRoute(bin2hex(random_bytes(10)), bin2hex(random_bytes(10)))->setRedirect($final);
        $route = static::newRoute($path)->setRedirect($middle);
        $page = static::newPage('route-page');
        $final->setPage($page);
        static::persistAndFlush($final, $middle, $route, $page);

        return ['route' => $route, 'middle' => $middle, 'final' => $final, 'page' => $page];
    }

    /**
     * @return array{parentRoute: Route, parentPageData: PageData, pageData: PageData, route: Route}
     */
    protected static function createPageDataNestedWithinRoute(string $childPath, string $parentPath): array
    {
        $parentPageData = new PageData();
        $parentPageData->page = $parentPage = static::newPage('parent page', true);
        $parentRoute = static::newRoute($parentPath)->setPageData($parentPageData);
        $pageData = new PageData();
        $pageData->page = $childPage = static::newPage('child page', true);
        $pageData->setParentPageData($parentPageData);
        $route = static::newRoute($childPath)->setPageData($pageData);
        static::persistAndFlush($parentPage, $parentPageData, $parentRoute, $childPage, $pageData, $route);

        return ['parentRoute' => $parentRoute, 'parentPageData' => $parentPageData, 'pageData' => $pageData, 'route' => $route];
    }

    /**
     * @return array{pageData: PageData, route: Route}
     */
    protected static function createPageDataWhoseParentHasNoRoute(string $path): array
    {
        $parentPageData = new PageData();
        $parentPageData->page = $parentPage = static::newPage('unrouted parent page', true);
        $pageData = new PageData();
        $pageData->page = $childPage = static::newPage('child page', true);
        $pageData->setParentPageData($parentPageData);
        $route = static::newRoute($path)->setPageData($pageData);
        static::persistAndFlush($parentPage, $parentPageData, $childPage, $pageData, $route);

        return ['pageData' => $pageData, 'route' => $route];
    }

    /**
     * @return array{parentRoute: Route, parentPage: Page, page: Page, route: Route}
     */
    protected static function createPageNestedWithinRoute(string $childPath, string $parentPath): array
    {
        $parentPage = static::newPage('parent page', true);
        $parentRoute = static::newRoute($parentPath)->setPage($parentPage);
        $page = static::newPage('child page', true);
        $page->setParentPage($parentPage);
        $route = static::newRoute($childPath)->setPage($page);
        static::persistAndFlush($parentPage, $parentRoute, $page, $route);

        return ['parentRoute' => $parentRoute, 'parentPage' => $parentPage, 'page' => $page, 'route' => $route];
    }

    /**
     * @return array{page: Page, route: Route}
     */
    protected static function createPageWhoseParentHasNoRoute(string $path): array
    {
        $parentPage = static::newPage('unrouted parent page', true);
        $page = static::newPage('child page', true);
        $page->setParentPage($parentPage);
        $route = static::newRoute($path)->setPage($page);
        static::persistAndFlush($parentPage, $page, $route);

        return ['page' => $page, 'route' => $route];
    }

    /**
     * @return array{grandparentRoute: Route, parentRoute: Route, pageData: PageData, route: Route}
     */
    protected static function createPageDataNestedWithinTwoRoutes(string $childPath, string $parentPath, string $grandparentPath): array
    {
        $grandparent = self::newRoutedPageData($grandparentPath, 'grandparent page', null);
        $parent = self::newRoutedPageData($parentPath, 'parent page', $grandparent['pageData']);
        $child = self::newRoutedPageData($childPath, 'child page', $parent['pageData']);
        static::persistAndFlush(...$grandparent['entities'], ...$parent['entities'], ...$child['entities']);

        return ['grandparentRoute' => $grandparent['route'], 'parentRoute' => $parent['route'], 'pageData' => $child['pageData'], 'route' => $child['route']];
    }

    /**
     * @return array{grandparentRoute: Route, pageData: PageData, route: Route}
     */
    protected static function createPageDataNestedWithinAnUnroutedPageWithinRoute(string $childPath, string $grandparentPath): array
    {
        $grandparent = self::newRoutedPageData($grandparentPath, 'grandparent page', null);
        $intermediatePageData = new PageData();
        $intermediatePageData->page = $intermediatePage = static::newPage('unrouted intermediate page', true);
        $intermediatePageData->setParentPageData($grandparent['pageData']);
        $child = self::newRoutedPageData($childPath, 'child page', $intermediatePageData);
        static::persistAndFlush(...[...$grandparent['entities'], $intermediatePage, $intermediatePageData, ...$child['entities']]);

        return ['grandparentRoute' => $grandparent['route'], 'pageData' => $child['pageData'], 'route' => $child['route']];
    }

    protected static function createRoutedPageAndUnroutedPageWhichAreEachOthersParent(string $path): Route
    {
        $routedPage = static::newPage('routed cycle page', true);
        $unroutedPage = static::newPage('unrouted cycle page', true);
        $routedPage->setParentPage($unroutedPage);
        $unroutedPage->setParentPage($routedPage);
        $route = static::newRoute($path)->setPage($routedPage);
        static::persistAndFlush($routedPage, $unroutedPage, $route);

        return $route;
    }

    protected static function createTwoPagesWhichAreEachOthersParent(string $pathOne, string $pathTwo): void
    {
        $pageOne = static::newPage('cycle page one', true);
        $pageTwo = static::newPage('cycle page two', true);
        $pageOne->setParentPage($pageTwo);
        $pageTwo->setParentPage($pageOne);
        static::persistAndFlush($pageOne, $pageTwo, static::newRoute($pathOne)->setPage($pageOne), static::newRoute($pathTwo)->setPage($pageTwo));
    }

    /**
     * @return array{parentTemplate: Page, parentPageData: PageData, parentComponent: DummyComponent, childPage: Page}
     */
    protected static function createRoutelessParentPageDataWithAnUnroutedChildPage(): array
    {
        $reachability = self::newReachabilityComponentGroup('unreachable parent group');
        $parentTemplate = static::newPage('routeless parent template', true);
        $parentTemplate->addComponentGroup($reachability['group']);
        $parentPageData = new PageData();
        $parentPageData->page = $parentTemplate;
        $childPage = static::newPage('unrouted child page');
        $childPage->setParentPageData($parentPageData);
        static::persistAndFlush(...[...$reachability['entities'], $parentTemplate, $parentPageData, $childPage]);

        return ['parentTemplate' => $parentTemplate, 'parentPageData' => $parentPageData, 'parentComponent' => $reachability['component'], 'childPage' => $childPage];
    }

    /**
     * @return array{page: Page, component: DummyComponent}
     */
    protected static function createRoutelessPageWithAComponentAndNoRoutedDescendant(): array
    {
        $reachability = self::newReachabilityComponentGroup('orphan group');
        $page = static::newPage('orphan routeless page');
        $page->addComponentGroup($reachability['group']);
        static::persistAndFlush(...[...$reachability['entities'], $page]);

        return ['page' => $page, 'component' => $reachability['component']];
    }

    /**
     * @return array{parentPage: Page, parentRoute: Route, page: Page, component: DummyComponent}
     */
    protected static function createRoutelessPageWithAComponentNestedWithinRoute(string $parentPath): array
    {
        $parentPage = static::newPage('routed parent page');
        $parentRoute = static::newRoute($parentPath)->setPage($parentPage);
        $reachability = self::newReachabilityComponentGroup('routeless nested group');
        $page = static::newPage('routeless nested page');
        $page->setParentPage($parentPage);
        $page->addComponentGroup($reachability['group']);
        static::persistAndFlush(...[$parentPage, $parentRoute, ...$reachability['entities'], $page]);

        return ['parentPage' => $parentPage, 'parentRoute' => $parentRoute, 'page' => $page, 'component' => $reachability['component']];
    }

    /**
     * @return array{pageData: PageDataWithComponent, draftComponent: DummyPublishableComponent, position: ComponentPosition, route: Route}
     */
    protected static function createPageDataWithADraftComponentInAPageDataPropertyPosition(string $path): array
    {
        $group = static::newComponentGroup('test');
        $position = self::newPageDataPropertyPosition($group, 'publishableComponent');
        $page = static::newPage('test page', true);
        $page->addComponentGroup($group);
        $draftComponent = new DummyPublishableComponent();
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $draftComponent;
        $pageData->page = $page;
        $route = static::newRoute($path)->setPageData($pageData);
        static::persistAndFlush($group, $position, $page, $draftComponent, $pageData, $route);

        return ['pageData' => $pageData, 'draftComponent' => $draftComponent, 'position' => $position, 'route' => $route];
    }

    /**
     * @return array{group: ComponentGroup, position: ComponentPosition, pageData: PageDataWithComponent, component: DummyComponent, route: Route}
     */
    protected static function createPageDataPropertyPositionWithAnAllowedComponentTypeInARestrictedGroup(string $path): array
    {
        $group = static::newComponentGroup('test');
        $group->allowedComponents = ['/component/dummy_components'];
        $position = self::newPageDataPropertyPosition($group, 'component');
        $page = static::newPage('test page', true);
        $page->addComponentGroup($group);
        $component = new DummyComponent();
        $pageData = new PageDataWithComponent();
        $pageData->component = $component;
        $pageData->page = $page;
        $route = static::newRoute($path)->setPageData($pageData);
        static::persistAndFlush($group, $position, $page, $component, $pageData, $route);

        return ['group' => $group, 'position' => $position, 'pageData' => $pageData, 'component' => $component, 'route' => $route];
    }

    /**
     * @return array{page: Page, pageData: PageData, route: Route, component: DummyComponent}
     */
    protected static function createComponentInAPageDataRoute(string $path): array
    {
        $page = static::newPage('page_data_page');
        $pageData = new PageData();
        $pageData->page = $page;
        $route = static::newRoute($path)->setPageData($pageData);
        $group = static::newComponentGroup('page_data_cc');
        $component = new DummyComponent();
        $position = static::newComponentPosition($group, $component, 0);
        $page->addComponentGroup($group);
        static::persistAndFlush($page, $pageData, $route, $group, $component, $position);

        return ['page' => $page, 'pageData' => $pageData, 'route' => $route, 'component' => $component];
    }

    protected static function placeComponentInARouteWithThePath(AbstractComponent $component, string $path): Route
    {
        $manager = static::getEntityManager();
        $component = $manager->find($component::class, $component->getId());
        \assert($component instanceof AbstractComponent);
        $page = static::newPage('page_1');
        $route = static::newRoute($path)->setPage($page);
        $group = static::newComponentGroup('test');
        $page->addComponentGroup($group);
        $position = static::newComponentPosition($group, $component, 0);
        static::persistAndFlush($page, $route, $group, $position);

        return $route;
    }

    protected static function createValidPageWithAComponentGroup(): Page
    {
        $layout = static::newLayout();
        $page = static::newPage('page-with-group');
        $page->uiComponent = 'TestComponent';
        $page->layout = $layout;
        $created = self::newComponentGroupWithComponents(1);
        $page->addComponentGroup($created['group']);
        static::persistAndFlush($layout, $page, $created['group'], ...$created['components'], ...$created['positions']);

        return $page;
    }

    protected static function setPageDataTitle(AbstractPageData $pageData, string $title): void
    {
        $manager = static::getEntityManager();
        $managed = $manager->find($pageData::class, $pageData->getId());
        \assert($managed instanceof AbstractPageData);
        $managed->setTitle($title);
        $manager->flush();
        static::rebootKernel();
    }

    protected static function markReachableWithoutRoute(AbstractPage $page): void
    {
        $manager = static::getEntityManager();
        $managed = $manager->find($page::class, $page->getId());
        \assert($managed instanceof AbstractPage);
        $managed->isReachableWithoutRoute = true;
        $manager->flush();
        static::rebootKernel();
    }

    protected static function assertRouteNamed(string $path, string $name): void
    {
        self::assertSame($name, static::routeAt($path)->getName(), \sprintf('The route "%s" is not named "%s".', $path, $name));
    }

    protected static function assertRouteLive(string $path): void
    {
        self::assertTrue(static::getContainer()->get(RouteLiveResolver::class)->isLive(static::routeAt($path)), \sprintf('The route "%s" is not live.', $path));
    }

    protected static function assertRouteNotLive(string $path): void
    {
        self::assertFalse(static::getContainer()->get(RouteLiveResolver::class)->isLive(static::routeAt($path)), \sprintf('The route "%s" is live.', $path));
    }

    protected static function assertRouteCount(int $count): void
    {
        self::assertSame($count, static::countEntities(Route::class));
    }

    /**
     * @return array{pageData: PageData, route: Route, entities: list<object>}
     */
    private static function newRoutedPageData(string $path, string $pageReference, ?PageData $parentPageData): array
    {
        $page = static::newPage($pageReference, true);
        $pageData = new PageData();
        $pageData->page = $page;
        if (null !== $parentPageData) {
            $pageData->setParentPageData($parentPageData);
        }
        $route = static::newRoute($path)->setPageData($pageData);

        return ['pageData' => $pageData, 'route' => $route, 'entities' => [$page, $pageData, $route]];
    }

    /**
     * @return array{group: ComponentGroup, component: DummyComponent, entities: list<object>}
     */
    private static function newReachabilityComponentGroup(string $reference): array
    {
        $component = new DummyComponent();
        $group = static::newComponentGroup($reference);
        $position = static::newComponentPosition($group, $component, 0);

        return ['group' => $group, 'component' => $component, 'entities' => [$component, $group, $position]];
    }

    private static function newPageDataPropertyPosition(ComponentGroup $group, string $property): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->pageDataProperty = $property;
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;

        return $position;
    }
}
