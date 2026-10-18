<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;

trait SiteTrait
{
    use ResourceTrait;

    protected static function createPage(string $reference = 'page', bool $isTemplate = false): Page
    {
        $page = static::newPage($reference, $isTemplate);
        static::persistAndFlush($page);

        return $page;
    }

    protected static function createLayout(string $reference = 'no-reference', ?string $uiComponent = null): Layout
    {
        $layout = static::newLayout($reference, $uiComponent);
        static::persistAndFlush($layout);

        return $layout;
    }

    protected static function createSiteConfigParameter(string $key = 'key', string $value = 'value'): SiteConfigParameter
    {
        $parameter = new SiteConfigParameter();
        $parameter->setKey($key)->setValue($value);
        static::persistAndFlush($parameter);

        return $parameter;
    }

    protected static function createRouteWithPage(string $path): Route
    {
        $page = static::newPage('route-page');
        $route = static::newRoute($path)->setPage($page);
        static::persistAndFlush($page, $route);

        return $route;
    }

    /**
     * @return array{group: ComponentGroup, components: list<DummyComponent>, positions: list<ComponentPosition>}
     */
    protected static function createComponentGroupWithComponents(int $count, string $reference = 'collection'): array
    {
        $created = self::newComponentGroupWithComponents($count, $reference);
        static::persistAndFlush($created['group'], ...$created['components'], ...$created['positions']);

        return $created;
    }

    /**
     * @return array{route: Route, layout: Layout, page: Page, group: ComponentGroup, component: AbstractComponent, position: ComponentPosition}
     */
    protected static function createRoutedPageWithComponent(string $path, ?AbstractComponent $component = null): array
    {
        $layout = static::newLayout('manifest-tag-layout');
        $page = static::newPage('manifest-tag-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = $layout;
        $group = static::newComponentGroup('manifest-tag-group');
        $page->addComponentGroup($group);
        $component ??= new DummyComponent();
        $position = static::newComponentPosition($group, $component, 0);
        $route = static::newRoute($path)->setPage($page);
        static::persistAndFlush($layout, $page, $group, $component, $position, $route);

        return ['route' => $route, 'layout' => $layout, 'page' => $page, 'group' => $group, 'component' => $component, 'position' => $position];
    }

    /**
     * @return array{route: Route, page: Page, group: ComponentGroup, component: DummyComponent, position: ComponentPosition}
     */
    protected static function createComponentInRoute(string $path): array
    {
        $page = static::newPage();
        $route = static::newRoute($path)->setPage($page);
        $created = self::newComponentGroupWithComponents(1);
        $page->addComponentGroup($created['group']);
        static::persistAndFlush($page, $route, $created['group'], ...$created['components'], ...$created['positions']);

        return ['route' => $route, 'page' => $page, 'group' => $created['group'], 'component' => $created['components'][0], 'position' => $created['positions'][0]];
    }

    /**
     * @return array{route: ?Route, pageData: PageDataWithComponent, page: Page, group: ComponentGroup, position: ComponentPosition, component: DummyComponent, publishableComponent: DummyPublishableComponent}
     */
    protected static function createPageDataWithRoute(?string $path): array
    {
        $group = static::newComponentGroup('test');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = static::newPage('test page', true);
        $page->addComponentGroup($group);
        $component = new DummyComponent();
        $publishableComponent = new DummyPublishableComponent();
        $pageData = new PageDataWithComponent();
        $pageData->component = $component;
        $pageData->publishableComponent = $publishableComponent;
        $pageData->page = $page;
        $route = null === $path ? null : static::newRoute($path)->setPageData($pageData);
        static::persistAndFlush(...array_filter([$group, $position, $page, $component, $publishableComponent, $pageData, $route]));

        return ['route' => $route, 'pageData' => $pageData, 'page' => $page, 'group' => $group, 'position' => $position, 'component' => $component, 'publishableComponent' => $publishableComponent];
    }

    protected static function routeAt(string $path): Route
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $route = $manager->getRepository(Route::class)->findOneBy(['path' => $path]);
        self::assertInstanceOf(Route::class, $route, \sprintf('No route has the path "%s".', $path));

        return $route;
    }

    protected static function setRouteLiveAt(string $path, ?\DateTimeImmutable $liveAt): void
    {
        static::routeAt($path)->setLiveAt($liveAt);
        static::getEntityManager()->flush();
        static::rebootKernel();
    }

    protected static function assertRouteRedirectsTo(string $path, string $redirectPath): void
    {
        self::assertSame($redirectPath, static::routeAt($path)->getRedirect()?->getPath(), \sprintf('The Route "%s" does not redirect to "%s".', $path, $redirectPath));
    }

    protected static function assertRouteDoesNotRedirect(string $path): void
    {
        self::assertNull(static::routeAt($path)->getRedirect(), \sprintf('The Route "%s" redirects.', $path));
    }

    protected static function newPage(string $reference = 'page', bool $isTemplate = false): Page
    {
        $page = new Page();
        $page->isTemplate = $isTemplate;
        $page->reference = $reference;

        return $page;
    }

    protected static function newLayout(string $reference = 'no-reference', ?string $uiComponent = null): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $layout->uiComponent = $uiComponent;

        return $layout;
    }

    protected static function newRoute(string $path, ?string $name = null): Route
    {
        return (new Route())->setPath($path)->setName($name ?? $path);
    }

    protected static function newComponentGroup(string $reference, ?string $location = null): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $location ?? $reference;

        return $group;
    }

    protected static function newComponentPosition(ComponentGroup $group, AbstractComponent $component, int $sortValue): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $component;
        $position->sortValue = $sortValue;

        return $position;
    }

    /**
     * @return array{group: ComponentGroup, components: list<DummyComponent>, positions: list<ComponentPosition>}
     */
    private static function newComponentGroupWithComponents(int $count, string $reference = 'collection'): array
    {
        $group = static::newComponentGroup($reference);
        $components = [];
        $positions = [];
        for ($sortValue = 0; $sortValue < $count; ++$sortValue) {
            $components[] = $component = new DummyComponent();
            $positions[] = static::newComponentPosition($group, $component, $sortValue);
        }

        return ['group' => $group, 'components' => $components, 'positions' => $positions];
    }
}
