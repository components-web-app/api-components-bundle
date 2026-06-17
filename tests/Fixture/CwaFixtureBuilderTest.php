<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;

class CwaFixtureBuilderTest extends TestCase
{
    private function makeBuilder(?ObjectManager $em = null, ?RouteGeneratorInterface $routeGenerator = null, ?IriConverterInterface $iriConverter = null): CwaFixtureBuilder
    {
        $builder = new CwaFixtureBuilder(
            $this->createStub(TimestampedDataPersister::class),
            $routeGenerator ?? $this->createStub(RouteGeneratorInterface::class),
            $iriConverter ?? $this->createStub(IriConverterInterface::class),
        );
        $builder->withManager($em ?? $this->createStub(ObjectManager::class));

        return $builder;
    }

    private function collectingEm(?array &$persisted = null): ObjectManager
    {
        $persisted ??= [];
        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(
            static function (object $e) use (&$persisted): void { $persisted[] = $e; }
        );

        return $em;
    }

    private function autoRouteGenerator(): RouteGeneratorInterface
    {
        $gen = $this->createStub(RouteGeneratorInterface::class);
        $gen->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = new Route();
            $route->setPath('/' . spl_object_id($entity));
            $route->setName((string) spl_object_id($entity));
            $entity->setRoute($route);

            return $route;
        });

        return $gen;
    }

    // --- Layout & Page ---

    public function test_layout_and_page_with_explicit_route_are_persisted(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/', routeName: 'home');
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));

        $this->assertCount(1, $layouts);
        $this->assertSame('CwaLayoutPrimary', $layouts[0]->uiComponent);
        $this->assertSame('main', $layouts[0]->reference);

        $this->assertCount(1, $pages);
        $this->assertSame('PrimaryPageTemplate', $pages[0]->uiComponent);
        $this->assertSame('home', $pages[0]->reference);
        $this->assertSame($layouts[0], $pages[0]->layout);
        $this->assertFalse($pages[0]->isTemplate);

        $this->assertCount(1, $routes);
        $this->assertSame('/', $routes[0]->getPath());
        $this->assertSame('home', $routes[0]->getName());
        $this->assertSame($routes[0], $pages[0]->getRoute());
        $this->assertSame($pages[0], $routes[0]->getPage());
    }

    public function test_template_page_has_no_route_created(): void
    {
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->never())->method('create');

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('blog-template', 'BlogTemplate', layout: 'main', isTemplate: true);
        $builder->flush();
    }

    public function test_page_without_route_and_not_template_calls_route_generator(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main');
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(1, $pages);
        $this->assertCount(1, $routes);
        $this->assertNotNull($pages[0]->getRoute());
    }

    public function test_named_route_is_accessible_via_get_route_after_flush(): void
    {
        $builder = $this->makeBuilder();
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/', routeName: 'home-page');
        $builder->flush();

        $route = $builder->getRoute('home-page');
        $this->assertSame('/', $route->getPath());
        $this->assertSame('home-page', $route->getName());
    }

    public function test_named_route_via_generator_is_accessible_after_flush(): void
    {
        $routeGenerator = $this->autoRouteGenerator();
        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'CwaLayoutPrimary');

        $pageData = new class extends AbstractPageData {};
        $builder->pageData($pageData, routeName: 'my-pagedata');
        $builder->flush();

        $route = $builder->getRoute('my-pagedata');
        $this->assertSame($route, $pageData->getRoute());
    }

    public function test_get_route_throws_before_flush(): void
    {
        $builder = $this->makeBuilder();
        $builder->page('home', 'Template', layout: 'main', route: '/', routeName: 'home-page');

        $this->expectException(\LogicException::class);
        $builder->getRoute('home-page');
    }

    // --- Layout groups ---

    public function test_layout_group_creates_component_group_with_correct_properties(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_/some_components'); // any call returns the stub IRI

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'CwaLayoutPrimary')->group('nav', allow: [\stdClass::class]);
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));

        $this->assertCount(1, $groups);
        $this->assertSame('layout:main/nav', $groups[0]->reference);
        $this->assertSame('nav', $groups[0]->location);
        $this->assertSame(['/_/some_components'], $groups[0]->allowedComponents);

        $this->assertCount(1, $layouts);
        $this->assertTrue($layouts[0]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->layouts->contains($layouts[0]));
    }

    // --- Page groups ---

    public function test_page_group_creates_component_group_linked_to_page(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));

        $this->assertCount(1, $groups);
        $this->assertSame('page:home/primary', $groups[0]->reference);
        $this->assertSame('primary', $groups[0]->location);
        $this->assertNull($groups[0]->allowedComponents);

        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->pages->contains($pages[0]));
    }

    public function test_group_add_creates_component_positions_with_auto_sort(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $component1 = new class extends AbstractComponent {};
        $component2 = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component1)
            ->add($component2);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $this->assertSame($component1, $positions[0]->component);
        $this->assertSame(10, $positions[0]->sortValue);
        $this->assertSame($component2, $positions[1]->component);
        $this->assertSame(20, $positions[1]->sortValue);
    }

    public function test_group_page_data_position_creates_positions_with_property(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('blog-template', 'BlogTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('image')
            ->pageDataPosition('htmlContent');
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $this->assertNull($positions[0]->component);
        $this->assertSame('image', $positions[0]->pageDataProperty);
        $this->assertSame(10, $positions[0]->sortValue);
        $this->assertSame('htmlContent', $positions[1]->pageDataProperty);
        $this->assertSame(20, $positions[1]->sortValue);
    }

    // --- PageData + templates ---

    public function test_pagedata_links_to_template_page(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('blog-template', 'BlogTemplate', layout: 'main', isTemplate: true);
        $builder->pageData($pageData, template: 'blog-template');
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertSame($pages[0], $pageData->page);
    }

    public function test_pagedata_with_explicit_route(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder($em);
        $builder->pageData($pageData, route: '/blog-articles/article-0', routeName: 'article-0');
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(1, $routes);
        $this->assertSame('/blog-articles/article-0', $routes[0]->getPath());
        $this->assertSame($pageData, $routes[0]->getPageData());
        $this->assertSame($routes[0], $builder->getRoute('article-0'));
    }

    // --- Nested relationships ---

    public function test_nested_pagedata_sets_parent_relationship(): void
    {
        $parentPageData = new class extends AbstractPageData {};
        $childPageData = new class extends AbstractPageData {};

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(static function (AbstractPageData $pd): Route {
                $route = new Route();
                $route->setPath('/' . spl_object_id($pd));
                $route->setName((string) spl_object_id($pd));
                $pd->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child) use ($childPageData): void {
                $child->pageData($childPageData);
            });
        $builder->flush();

        $this->assertSame($parentPageData, $childPageData->getParentPageData());
    }

    public function test_nested_page_under_pagedata_sets_parent_relationship(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $parentPageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'ChapterTemplate', layout: 'main');
            });
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertSame($parentPageData, $pages[0]->getParentPageData());
    }

    public function test_nested_page_under_page_sets_parent_relationship(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('parent', 'ParentTemplate', layout: 'main')
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('child', 'ChildTemplate', layout: 'main');
            });
        $builder->flush();

        $pages = array_column(
            array_filter($persisted, static fn ($e) => $e instanceof Page),
            null,
            'reference'
        );
        $this->assertArrayHasKey('parent', $pages);
        $this->assertArrayHasKey('child', $pages);
        $this->assertSame($pages['parent'], $pages['child']->getParentPage());
    }

    // --- Route ordering ---

    public function test_parent_route_is_created_before_child_route(): void
    {
        $createOrder = [];
        $parentPageData = new class extends AbstractPageData {};

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->method('create')
            ->willReturnCallback(static function (object $entity) use (&$createOrder): Route {
                $createOrder[] = spl_object_id($entity);
                $route = new Route();
                $route->setPath('/' . spl_object_id($entity));
                $route->setName((string) spl_object_id($entity));
                $entity->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'ChapterTemplate', layout: 'main');
            });
        $builder->flush();

        $this->assertCount(2, $createOrder);
        $this->assertSame(spl_object_id($parentPageData), $createOrder[0]);
    }

    // --- Association graph auto-persist ---

    public function test_public_persist_persists_entity_via_manager(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->persist($component);
        $builder->flush();

        $this->assertContains($component, $persisted);
    }

    public function test_flush_second_call_only_processes_new_positions(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $component1 = new class extends AbstractComponent {};
        $component2 = new class extends AbstractComponent {};

        $routeGenerator = $this->autoRouteGenerator();
        $builder = $this->makeBuilder($em, $routeGenerator);
        $builder->layout('main', 'CwaLayoutPrimary');
        $navGroup = $builder->layout('main', 'CwaLayoutPrimary')->group('nav');
        $builder->page('home', 'Template', layout: 'main', route: '/');

        $navGroup->add($component1);
        $builder->flush(); // first flush — component1 processed

        $navGroup->add($component2);
        $builder->flush(); // second flush — only component2 should be processed

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        // component1 → position1 in flush 1; component2 → position2 in flush 2
        $this->assertCount(2, $positions);
        $components = array_map(static fn ($p) => $p->component, $positions);
        $this->assertContains($component1, $components);
        $this->assertContains($component2, $components);
    }

    public function test_phases_one_to_three_run_only_once_across_multiple_flushes(): void
    {
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->once()) // must be called exactly once despite two flush() calls
            ->method('create')
            ->willReturnCallback(static function (object $entity): Route {
                $route = new Route();
                $route->setPath('/' . spl_object_id($entity));
                $route->setName((string) spl_object_id($entity));
                $entity->setRoute($route);
                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'CwaLayoutPrimary');
        $builder->page('home', 'Template', layout: 'main'); // will call routeGenerator->create() once

        $builder->flush();
        $builder->flush(); // second flush must NOT call routeGenerator->create() again
    }

    public function test_parent_pagedata_route_created_before_child_pagedata_route(): void
    {
        $createOrder = [];
        $parentPageData = new class extends AbstractPageData {};
        $childPageData = new class extends AbstractPageData {};

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->method('create')
            ->willReturnCallback(static function (object $entity) use (&$createOrder): Route {
                $createOrder[] = spl_object_id($entity);
                $route = new Route();
                $route->setPath('/' . spl_object_id($entity));
                $route->setName((string) spl_object_id($entity));
                $entity->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child) use ($childPageData): void {
                $child->pageData($childPageData);
            });
        $builder->flush();

        $this->assertCount(2, $createOrder);
        $this->assertSame(spl_object_id($parentPageData), $createOrder[0]);
        $this->assertSame(spl_object_id($childPageData), $createOrder[1]);
    }
}
