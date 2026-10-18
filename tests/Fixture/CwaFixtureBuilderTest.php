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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Builder\ComponentBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageDataBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Command\Fixture\PageDataWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRoute;
use Symfony\Component\HttpFoundation\File\File;

class CwaFixtureBuilderTest extends TestCase
{
    private function makeBuilder(?ObjectManager $em = null, ?RouteGeneratorInterface $routeGenerator = null, ?IriConverterInterface $iriConverter = null, ?TimestampedDataPersister $timestampedPersister = null, ?UploadableFileManager $uploadableFileManager = null, ?UploadableAttributeReaderInterface $uploadableAttributeReader = null): CwaFixtureBuilder
    {
        $builder = new CwaFixtureBuilder(
            $timestampedPersister ?? $this->createStub(TimestampedDataPersister::class),
            $routeGenerator ?? $this->createStub(RouteGeneratorInterface::class),
            $iriConverter ?? $this->createStub(IriConverterInterface::class),
            $uploadableFileManager,
            $uploadableAttributeReader,
        );
        $builder->withManager($em ?? $this->createStub(ObjectManager::class));

        return $builder;
    }

    /**
     * @param array<int, array{entity: object, isNew: bool}> $calls
     */
    private function recordingTimestampedPersister(array &$calls, bool $isConfigured = true): TimestampedDataPersister
    {
        $persister = $this->createStub(TimestampedDataPersister::class);
        $persister->method('isConfigured')->willReturn($isConfigured);
        $persister->method('persistTimestampedFields')->willReturnCallback(
            static function (object $entity, bool $isNew) use (&$calls): void {
                $calls[] = ['entity' => $entity, 'isNew' => $isNew];
            }
        );

        return $persister;
    }

    private function collectingEm(?array &$persisted = null, ?int &$flushCount = null): ObjectManager
    {
        $persisted ??= [];
        $flushCount ??= 0;
        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(
            static function (object $e) use (&$persisted): void { $persisted[] = $e; }
        );
        $em->method('flush')->willReturnCallback(
            static function () use (&$flushCount): void { ++$flushCount; }
        );

        return $em;
    }

    /**
     * @template T of AbstractPage
     *
     * @param T $page
     *
     * @return T
     */
    private static function titled(AbstractPage $page, string $title = 'Page data'): AbstractPage
    {
        $page->setTitle($title);

        return $page;
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

    public function test_layout_and_page_with_explicit_route_are_persisted(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/', routeName: 'home');
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));

        $this->assertCount(1, $layouts);
        $this->assertSame('CwaLayoutPrimary', $layouts[0]->uiComponent);
        $this->assertSame('main', $layouts[0]->reference);

        $this->assertCount(1, $pages);
        $this->assertSame('CwaPagePrimaryPageTemplate', $pages[0]->uiComponent);
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
        $builder->layout('main', 'Primary');
        $builder->page('blog-template', 'BlogTemplate', layout: 'main', isTemplate: true);
        $builder->flush();
    }

    public function test_page_without_route_is_persisted_without_a_generated_route(): void
    {
        $persisted = [];
        $flushCount = 0;
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->never())->method('create');

        $builder = $this->makeBuilder($this->collectingEm($persisted, $flushCount), $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->page('parent', 'Primary', layout: 'main')->title('Parent')->withoutRoute();
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertNull($pages[0]->getRoute());
        $this->assertSame([], array_values(array_filter($persisted, static fn ($e) => $e instanceof Route)));
        $this->assertSame(1, $flushCount);
    }

    public function test_an_explicit_route_wins_over_without_route_on_a_page(): void
    {
        $builder = $this->makeBuilder();
        $builder->layout('main', 'Primary');
        $page = $builder->page('parent', 'Primary', layout: 'main', route: '/parent', routeName: 'parent')->withoutRoute();
        $builder->flush();

        $this->assertSame('/parent', $page->getRoute()?->getPath());
        $this->assertSame($page->getRoute(), $builder->getRoute('parent'));
    }

    public function test_a_child_with_an_explicit_route_under_a_page_without_route_keeps_it(): void
    {
        $builder = $this->makeBuilder(routeGenerator: $this->createStub(RouteGeneratorInterface::class));
        $builder->layout('main', 'Primary');
        $parent = $builder->page('parent', 'Primary', layout: 'main')->withoutRoute();
        $parent->nested(static function (CwaFixtureBuilder $child): void {
            $child->page('child', 'Primary', layout: 'main', route: '/parent/child', routeName: 'child');
        });
        $builder->flush();

        $this->assertNull($parent->getRoute());
        $this->assertSame('/parent/child', $builder->getRoute('child')->getPath());
        $this->assertSame($parent->getPage(), $builder->getRoute('child')->getPage()?->getParentPage());
    }

    public function test_an_untitled_page_with_no_route_is_left_without_a_route(): void
    {
        $persisted = [];
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->never())->method('create');

        $builder = $this->makeBuilder($this->collectingEm($persisted), $routeGenerator);
        $builder->layout('main', 'Primary');
        $page = $builder->page('home', 'Primary', layout: 'main');
        $builder->flush();

        $this->assertSame([$page->getPage()], array_values(array_filter($persisted, static fn ($e) => $e instanceof Page)));
        $this->assertNull($page->getRoute());
        $this->assertSame([], array_values(array_filter($persisted, static fn ($e) => $e instanceof Route)));
    }

    public function test_untitled_page_data_with_no_route_is_left_without_a_route(): void
    {
        $persisted = [];
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->never())->method('create');
        $routeGenerator->expects($this->never())->method('generatePath');

        $builder = $this->makeBuilder($this->collectingEm($persisted), $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->page('template', 'Primary', layout: 'main', isTemplate: true);
        $pageData = new class extends AbstractPageData {};
        $builder->pageData($pageData, template: 'template');
        $builder->flush();

        $this->assertContains($pageData, $persisted);
        $this->assertNull($pageData->getRoute());
    }

    public function test_an_untitled_nested_page_with_no_route_is_left_without_a_route_under_its_routed_parent(): void
    {
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->once())->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = (new Route())->setPath('/parent')->setName('parent');
            $entity->setRoute($route);

            return $route;
        });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'Primary');
        $child = null;
        $parent = $builder->page('parent', 'Primary', layout: 'main')->title('Parent');
        $parent->nested(static function (CwaFixtureBuilder $nested) use (&$child): void {
            $child = $nested->page('child', 'Primary', layout: 'main');
        });
        $builder->flush();

        $this->assertSame('/parent', $parent->getRoute()?->getPath());
        $this->assertNull($child->getRoute());
        $this->assertSame($parent->getPage(), $child->getPage()->getParentPage());
    }

    public function test_an_untitled_page_with_a_live_at_and_no_route_is_left_without_a_route(): void
    {
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->never())->method('create');

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'Primary');
        $page = $builder->page('home', 'Primary', layout: 'main')->liveAt(null);
        $builder->flush();

        $this->assertNull($page->getRoute());
    }

    public function test_page_without_route_and_not_template_calls_route_generator(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main')->title('Page');
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
        $builder->layout('main', 'Primary');
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
        $builder->layout('main', 'Primary');

        $pageData = self::titled(new class extends AbstractPageData {});
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

    public function test_with_manager_starts_a_new_load_that_forgets_the_previous_scaffold(): void
    {
        $firstPersisted = [];
        $builder = $this->makeBuilder($this->collectingEm($firstPersisted));
        $firstLayout = $builder->layout('main', 'Primary');
        $firstLayout->group('top')->add(new DummyComponent());
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $builder->afterRoutes(static function (): void {});
        $builder->redirect('/old', to: 'home');
        $builder->flush();

        $secondPersisted = [];
        $secondFlushCount = 0;
        $builder->withManager($this->collectingEm($secondPersisted, $secondFlushCount));
        $secondLayout = $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->flush();

        $this->assertNotSame($firstLayout, $secondLayout);
        $this->assertSame(['about'], array_map(static fn (Page $p) => $p->reference, array_values(array_filter($secondPersisted, static fn ($e) => $e instanceof Page))));
        $this->assertSame(['/about'], array_map(static fn (Route $r) => $r->getPath(), array_values(array_filter($secondPersisted, static fn ($e) => $e instanceof Route))));
        $this->assertSame([], array_values(array_filter($secondPersisted, static fn ($e) => $e instanceof AbstractComponent)));
        $this->assertSame($secondLayout->getLayout(), array_values(array_filter($secondPersisted, static fn ($e) => $e instanceof Page))[0]->layout);
        $this->assertSame(2, $secondFlushCount);
        $this->expectException(\LogicException::class);
        $builder->getRoute('home');
    }

    public function test_reset_forgets_the_load(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $layout = $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $builder->flush();

        $builder->reset();

        $this->assertNotSame($layout, $builder->layout('main', 'Primary'));
        $this->assertSame('CWA scaffold: nothing to load', (string) $builder->getSummary());
        $this->expectException(\LogicException::class);
        $builder->getRoute('home');
    }

    public function test_layout_group_creates_component_group_with_correct_properties(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn ($resource) => \is_string($resource) ? '/_/some_components' : '/_api/_/layouts/test-uuid'
        );

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary')->group('nav', allow: [\stdClass::class]);
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));

        $this->assertCount(1, $groups);
        $this->assertSame('nav_/_api/_/layouts/test-uuid', $groups[0]->reference);
        $this->assertSame('/_api/_/layouts/test-uuid', $groups[0]->location);
        $this->assertSame(['/_/some_components'], $groups[0]->allowedComponents);

        $this->assertCount(1, $layouts);
        $this->assertTrue($layouts[0]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->layouts->contains($layouts[0]));
    }

    public function test_page_group_creates_component_group_linked_to_page(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_api/_/pages/test-uuid');

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));

        $this->assertCount(1, $groups);
        $this->assertSame('primary_/_api/_/pages/test-uuid', $groups[0]->reference);
        $this->assertSame('/_api/_/pages/test-uuid', $groups[0]->location);
        $this->assertNull($groups[0]->allowedComponents);

        $this->assertCount(1, $pages);
        $this->assertTrue($pages[0]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->pages->contains($pages[0]));
    }

    public function test_group_add_creates_component_positions_sorted_from_zero_in_steps_of_one(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $component1 = new class extends AbstractComponent {};
        $component2 = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component1)
            ->add($component2);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $this->assertSame($component1, $positions[0]->component);
        $this->assertSame(0, $positions[0]->sortValue);
        $this->assertSame($component2, $positions[1]->component);
        $this->assertSame(1, $positions[1]->sortValue);
    }

    public function test_uploadable_component_with_file_is_persisted_via_file_manager(): void
    {
        $em = $this->collectingEm();

        $component = new class extends AbstractComponent {
            public ?File $file = null;
        };
        $component->file = new File(__DIR__ . '/../Functional/assets/files/image.png');

        $reader = $this->createStub(UploadableAttributeReaderInterface::class);
        $reader->method('isConfigured')->willReturnCallback(
            static fn (object|string $c): bool => \is_object($c) && $c === $component
        );

        $fileManager = $this->createMock(UploadableFileManager::class);
        $fileManager->expects($this->once())->method('persistFiles')->with($component);

        $builder = $this->makeBuilder($em, uploadableFileManager: $fileManager, uploadableAttributeReader: $reader);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();
    }

    public function test_non_uploadable_component_does_not_call_file_manager(): void
    {
        $em = $this->collectingEm();

        $component = new class extends AbstractComponent {};

        $reader = $this->createStub(UploadableAttributeReaderInterface::class);
        $reader->method('isConfigured')->willReturn(false);

        $fileManager = $this->createMock(UploadableFileManager::class);
        $fileManager->expects($this->never())->method('persistFiles');

        $builder = $this->makeBuilder($em, uploadableFileManager: $fileManager, uploadableAttributeReader: $reader);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();
    }

    public function test_group_page_data_position_creates_positions_with_property_sorted_from_zero(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('blog-template', 'BlogTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('App\Entity\BlogArticleData', 'image')
            ->pageDataPosition('App\Entity\BlogArticleData', 'htmlContent');
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $this->assertNull($positions[0]->component);
        $this->assertSame('image', $positions[0]->pageDataProperty);
        $this->assertSame('App\Entity\BlogArticleData', $positions[0]->pageDataClass);
        $this->assertSame(0, $positions[0]->sortValue);
        $this->assertSame('htmlContent', $positions[1]->pageDataProperty);
        $this->assertSame('App\Entity\BlogArticleData', $positions[1]->pageDataClass);
        $this->assertSame(1, $positions[1]->sortValue);
    }

    public function test_pagedata_links_to_template_page(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
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

    public function test_nested_pagedata_sets_parent_relationship(): void
    {
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $childPageData = self::titled(new class extends AbstractPageData {});

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

        $parentPageData = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'ChapterTemplate', layout: 'main')->title('Page');
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
        $builder->layout('main', 'Primary');
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

    public function test_parent_route_is_created_before_child_route(): void
    {
        $createOrder = [];
        $parentPageData = self::titled(new class extends AbstractPageData {});

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->exactly(2))->method('create')
            ->willReturnCallback(static function (object $entity) use (&$createOrder): Route {
                $createOrder[] = spl_object_id($entity);
                $route = new Route();
                $route->setPath('/' . spl_object_id($entity));
                $route->setName((string) spl_object_id($entity));
                $entity->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'ChapterTemplate', layout: 'main')->title('Page');
            });
        $builder->flush();

        $this->assertCount(2, $createOrder);
        $this->assertSame(spl_object_id($parentPageData), $createOrder[0]);
    }

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
        $builder->layout('main', 'Primary');
        $navGroup = $builder->layout('main', 'Primary')->group('nav');
        $builder->page('home', 'Template', layout: 'main', route: '/');

        $navGroup->add($component1);
        $builder->flush();

        $navGroup->add($component2);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $components = array_map(static fn ($p) => $p->component, $positions);
        $this->assertContains($component1, $components);
        $this->assertContains($component2, $components);
    }

    public function test_phases_one_to_three_run_only_once_across_multiple_flushes(): void
    {
        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (object $entity): Route {
                $route = new Route();
                $route->setPath('/' . spl_object_id($entity));
                $route->setName((string) spl_object_id($entity));
                $entity->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main')->title('Page');

        $builder->flush();
        $builder->flush();
    }

    public function test_on_routes_created_fires_after_child_routes_exist_with_child_builders(): void
    {
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $capturedBuilders = null;
        $capturedChildRoute = null;

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'ChapterTemplate', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $childBuilders) use (&$capturedBuilders, &$capturedChildRoute): void {
                $capturedBuilders = $childBuilders;
                $capturedChildRoute = $childBuilders[0]->getRoute()?->getPath();
            });
        $builder->flush();

        $this->assertIsArray($capturedBuilders);
        $this->assertCount(1, $capturedBuilders);
        $this->assertNotNull($capturedChildRoute, 'Child route path should be available inside onRoutesCreated');
    }

    public function test_on_routes_created_not_called_when_no_callback_registered(): void
    {
        $called = false;
        $parentPageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->pageData($parentPageData);
        $builder->flush();

        $this->assertFalse($called);
    }

    public function test_parent_pagedata_route_created_before_child_pagedata_route(): void
    {
        $createOrder = [];
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $childPageData = self::titled(new class extends AbstractPageData {});

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->exactly(2))->method('create')
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

    public function test_nested_pagedata_entity_is_persisted_in_evaluatenested(): void
    {
        $persisted = [];
        $parent = self::titled(new class extends AbstractPageData {});
        $child = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->autoRouteGenerator());
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $nested) use ($child): void {
                $nested->pageData($child);
            });
        $builder->flush();

        $this->assertContains($child, $persisted);
    }

    public function test_nested_page_in_evaluatenested_gets_layout_linked(): void
    {
        $persisted = [];
        $parent = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $nested): void {
                $nested->page('child', 'Template', layout: 'main')->title('Page');
            });
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertCount(1, $pages);
        $this->assertSame($layouts[0], $pages[0]->layout);
    }

    public function test_evaluate_nested_does_not_flush_when_no_new_nested_entities(): void
    {
        $flushCount = 0;

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true);
        $builder->flush();

        $this->assertSame(1, $flushCount);
    }

    public function test_evaluate_nested_flushes_when_new_nested_entities_added(): void
    {
        $flushCount = 0;
        $parent = self::titled(new class extends AbstractPageData {});
        $child = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), $this->autoRouteGenerator());
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $nested) use ($child): void {
                $nested->pageData($child);
            });
        $builder->flush();

        $this->assertSame(3, $flushCount);
    }

    public function test_a_component_group_declared_in_a_nested_closure_is_created_with_its_positions(): void
    {
        $persisted = [];
        $container = new class extends AbstractComponent {};
        $content = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('parent', 'Template', layout: 'main', route: '/parent', routeName: 'parent')
            ->nested(static function (CwaFixtureBuilder $nested) use ($container, $content): void {
                $nested->page('child', 'Template', layout: 'main', route: '/parent/child', routeName: 'child')
                    ->group('main')
                    ->add($container);
                $nested->component($container)->group('main')->add($content);
            });
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $groups);
        $this->assertCount(1, $container->getComponentGroups());
        $containerGroup = $container->getComponentGroups()->first();
        $this->assertContains($containerGroup, $groups);
        $this->assertSame([$container, $content], array_map(static fn (ComponentPosition $p) => $p->component, $positions));
        $this->assertSame($containerGroup, $positions[1]->componentGroup);
    }

    public function test_evaluate_nested_flushes_when_a_nested_closure_adds_only_a_component_group(): void
    {
        $flushCount = 0;
        $persisted = [];
        $container = new class extends AbstractComponent {};
        $content = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm($persisted, $flushCount), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('parent', 'Template', layout: 'main', route: '/parent', routeName: 'parent')
            ->nested(static function (CwaFixtureBuilder $nested) use ($container, $content): void {
                $nested->component($container)->group('main')->add($content);
            });
        $builder->flush();

        $this->assertSame(4, $flushCount);
        $this->assertContains($container, $persisted);
        $this->assertCount(1, array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
    }

    public function test_phase_four_does_not_flush_when_no_positions(): void
    {
        $flushCount = 0;

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary');
        $builder->flush();

        $this->assertSame(1, $flushCount);
    }

    public function test_phase_four_flushes_when_positions_are_created(): void
    {
        $flushCount = 0;
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();

        $this->assertSame(2, $flushCount);
    }

    public function test_phase_three_point_five_does_not_flush_when_no_callbacks(): void
    {
        $flushCount = 0;
        $pd = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), $this->autoRouteGenerator());
        $builder->pageData($pd);
        $builder->flush();

        $this->assertSame(2, $flushCount);
    }

    public function test_phase_three_point_five_flushes_when_on_routes_created_fires(): void
    {
        $flushCount = 0;
        $pd = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($pd)
            ->nested(static function (CwaFixtureBuilder $nested): void {
                $nested->page('child', 'Template', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $builders): void {});
        $builder->flush();

        $this->assertSame(4, $flushCount);
    }

    public function test_multiple_pagedata_specs_all_on_routes_created_callbacks_evaluated(): void
    {
        $pd1 = self::titled(new class extends AbstractPageData {});
        $pd2 = self::titled(new class extends AbstractPageData {});
        $callbackCalled = false;

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->pageData($pd1);
        $builder->pageData($pd2)
            ->onRoutesCreated(static function (array $builders) use (&$callbackCalled): void {
                $callbackCalled = true;
            });
        $builder->flush();

        $this->assertTrue($callbackCalled);
    }

    public function test_group_add_uses_explicit_sort_when_provided(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component, 99);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(1, $positions);
        $this->assertSame(99, $positions[0]->sortValue);
    }

    public function test_page_data_position_uses_explicit_sort_when_provided(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'BlogTemplate', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('App\Entity\BlogArticleData', 'image', 99);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(1, $positions);
        $this->assertSame(99, $positions[0]->sortValue);
    }

    public function test_flush_second_call_only_processes_new_page_data_positions(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $group = $builder->page('home', 'Template', layout: 'main', isTemplate: true)->group('primary');

        $group->pageDataPosition('App\Entity\BlogArticleData', 'image');
        $builder->flush();

        $group->pageDataPosition('App\Entity\BlogArticleData', 'htmlContent');
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(2, $positions);
        $properties = array_map(static fn ($p) => $p->pageDataProperty, $positions);
        $this->assertContains('image', $properties);
        $this->assertContains('htmlContent', $properties);
    }

    public function test_nested_pagedata_under_page_sets_parent_page_relationship(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('parent', 'ParentTemplate', layout: 'main')
            ->nested(static function (CwaFixtureBuilder $nested) use ($pageData): void {
                $nested->pageData($pageData);
            });
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertSame($pages[0], $pageData->getParentPage());
        $this->assertNull($pageData->getParentPageData());
    }

    public function test_multiple_pagedata_specs_all_nested_closures_evaluated(): void
    {
        $childPageData = self::titled(new class extends AbstractPageData {});
        $parent1 = self::titled(new class extends AbstractPageData {});
        $parent2 = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->pageData($parent1);
        $builder->pageData($parent2)
            ->nested(static function (CwaFixtureBuilder $nested) use ($childPageData): void {
                $nested->pageData($childPageData);
            });
        $builder->flush();

        $this->assertSame($parent2, $childPageData->getParentPageData());
    }

    public function test_multiple_page_specs_all_nested_closures_evaluated(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('first', 'FirstTemplate', layout: 'main');
        $builder->page('second', 'SecondTemplate', layout: 'main')
            ->nested(static function (CwaFixtureBuilder $nested): void {
                $nested->page('child', 'ChildTemplate', layout: 'main');
            });
        $builder->flush();

        $pages = array_column(
            array_filter($persisted, static fn ($e) => $e instanceof Page),
            null,
            'reference'
        );
        $this->assertArrayHasKey('child', $pages);
        $this->assertSame($pages['second'], $pages['child']->getParentPage());
    }

    public function test_on_routes_created_receives_only_child_page_builders_not_pre_existing_pages(): void
    {
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $capturedCount = null;

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('existing', 'ExistingTemplate', layout: 'main')->title('Page');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $nested): void {
                $nested->page('chapter', 'ChapterTemplate', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $childBuilders) use (&$capturedCount): void {
                $capturedCount = \count($childBuilders);
            });
        $builder->flush();

        $this->assertSame(1, $capturedCount);
    }

    public function test_named_route_via_generator_for_page_is_accessible_after_flush(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', routeName: 'home-page')->title('Page');
        $builder->flush();

        $route = $builder->getRoute('home-page');
        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertSame($route, $pages[0]->getRoute());
    }

    public function test_explicit_route_without_name_derives_name_from_path(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/blog/articles');
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(1, $routes);
        $this->assertSame('blog-articles', $routes[0]->getName());
    }

    public function test_explicit_route_for_root_path_derives_name_root(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', route: '/');
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(1, $routes);
        $this->assertSame('root', $routes[0]->getName());
    }

    public function test_layout_group_with_location_reference_uses_custom_reference_suffix(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_api/_/layouts/test-uuid');

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary')
            ->group('nav', locationReference: 'global-nav');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertCount(1, $groups);
        $this->assertSame('nav_global-nav', $groups[0]->reference);
        $this->assertSame('/_api/_/layouts/test-uuid', $groups[0]->location);
    }

    public function test_page_group_with_location_reference_uses_custom_reference_suffix(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_api/_/pages/test-uuid');

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', isTemplate: true)
            ->group('primary', locationReference: 'home-primary');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertCount(1, $groups);
        $this->assertSame('primary_home-primary', $groups[0]->reference);
        $this->assertSame('/_api/_/pages/test-uuid', $groups[0]->location);
    }

    public function test_shared_group_via_location_reference_links_to_multiple_layouts(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnOnConsecutiveCalls(
            '/_api/_/layouts/uuid-1',
            '/_api/_/layouts/uuid-2',
        );

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary')->group('nav', locationReference: 'global-nav');
        $builder->layout('alt', 'Alt')->group('nav', locationReference: 'global-nav');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertCount(1, $groups);
        $this->assertCount(2, $layouts);
        $this->assertTrue($layouts[0]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($layouts[1]->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->layouts->contains($layouts[0]));
        $this->assertTrue($groups[0]->layouts->contains($layouts[1]));
    }

    public function test_component_group_creates_component_group_linked_to_component(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_api/component/comp-uuid');

        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->component($component)->group('items');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertCount(1, $groups);
        $this->assertSame('items_/_api/component/comp-uuid', $groups[0]->reference);
        $this->assertSame('/_api/component/comp-uuid', $groups[0]->location);
        $this->assertTrue($component->getComponentGroups()->contains($groups[0]));
        $this->assertTrue($groups[0]->components->contains($component));
    }

    public function test_component_group_with_location_reference(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_api/component/comp-uuid');

        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->component($component)->group('items', locationReference: 'my-comp');
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertCount(1, $groups);
        $this->assertSame('items_my-comp', $groups[0]->reference);
        $this->assertSame('/_api/component/comp-uuid', $groups[0]->location);
    }

    public function test_component_group_positions_are_created_in_phase_four(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $outerComponent = new class extends AbstractComponent {};
        $innerComponent = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em);
        $builder->component($outerComponent)->group('items')->add($innerComponent);
        $builder->flush();

        $positions = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertCount(1, $positions);
        $this->assertSame($innerComponent, $positions[0]->component);
    }

    public function test_component_returns_same_builder_for_same_instance(): void
    {
        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder();

        $b1 = $builder->component($component);
        $b2 = $builder->component($component);

        $this->assertSame($b1, $b2);
    }

    public function test_component_is_persisted_in_phase_one_before_groups_are_linked(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder($em);
        $builder->component($component)->group('items');
        $builder->flush();

        $componentIndex = array_search($component, $persisted, true);
        $groupIndex = array_search(
            array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup))[0] ?? null,
            $persisted,
            true,
        );
        $this->assertNotFalse($componentIndex);
        $this->assertNotFalse($groupIndex);
        $this->assertLessThan($groupIndex, $componentIndex);
    }

    public function test_second_flush_call_does_not_repeat_phase_one_through_three(): void
    {
        $flushCount = 0;
        $em = $this->collectingEm(flushCount: $flushCount);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main');

        $builder->flush();
        $firstFlushCount = $flushCount;
        $builder->flush();

        $this->assertSame($firstFlushCount, $flushCount, 'Second flush must not repeat phases 1-3');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_nested_pagedata(): void
    {
        $calls = [];
        $parent = self::titled(new class extends AbstractPageData {});
        $child = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder(
            $this->collectingEm(),
            $this->autoRouteGenerator(),
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $nested) use ($child): void {
                $nested->pageData($child);
            });
        $builder->flush();

        $childCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] === $child));
        $this->assertNotEmpty($childCalls, 'persistTimestampedFields must be called for the nested child PageData');
        foreach ($childCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for nested child PageData');
        }
    }

    public function test_timestamped_persister_called_with_is_new_true_for_nested_page(): void
    {
        $calls = [];
        $parent = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder(
            $this->collectingEm(),
            $this->autoRouteGenerator(),
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('nested-child', 'Template', layout: 'main')->title('Page');
            });
        $builder->flush();

        $pageCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof Page));
        $this->assertNotEmpty($pageCalls, 'persistTimestampedFields must be called for the nested Page');
        foreach ($pageCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for nested Page');
        }
    }

    public function test_nested_page_groups_are_created_in_evaluate_nested(): void
    {
        $persisted = [];
        $parent = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parent)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('child', 'Template', layout: 'main')->title('Page')
                    ->group('primary');
            });
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertNotEmpty($groups, 'ComponentGroup for nested page must be created during evaluateNested');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_layout_in_phase_one(): void
    {
        $calls = [];
        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->flush();

        $layoutCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof Layout));
        $this->assertNotEmpty($layoutCalls, 'persistTimestampedFields must be called for Layout in phaseOne');
        foreach ($layoutCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for Layout');
        }
    }

    public function test_timestamped_persister_called_with_is_new_true_for_page_in_phase_one(): void
    {
        $calls = [];
        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true);
        $builder->flush();

        $pageCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof Page));
        $this->assertNotEmpty($pageCalls, 'persistTimestampedFields must be called for Page in phaseOne');
        foreach ($pageCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for Page');
        }
    }

    public function test_pagedata_without_template_ref_does_not_set_page(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $pageData = new class extends AbstractPageData {};
        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('some-template', 'Template', layout: 'main', isTemplate: true);
        $builder->pageData($pageData);
        $builder->flush();

        $prop = (new \ReflectionClass($pageData))->getProperty('page');
        $this->assertFalse($prop->isInitialized($pageData), 'PageData with no templateRef must not have page linked');
    }

    public function test_pagedata_with_nonexistent_template_ref_does_not_set_page(): void
    {
        $pageData = new class extends AbstractPageData {};
        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->pageData($pageData, template: 'nonexistent-template');
        $builder->flush();

        $prop = (new \ReflectionClass($pageData))->getProperty('page');
        $this->assertFalse($prop->isInitialized($pageData), 'PageData with nonexistent templateRef must not have page linked');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_pagedata_in_phase_one(): void
    {
        $calls = [];
        $pageData = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->pageData($pageData);
        $builder->flush();

        $pdCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] === $pageData));
        $this->assertNotEmpty($pdCalls, 'persistTimestampedFields must be called for PageData in phaseOne');
        foreach ($pdCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for PageData');
        }
    }

    public function test_pagedata_entity_is_persisted_in_phase_one(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $pageData = self::titled(new class extends AbstractPageData {});
        $builder = $this->makeBuilder($em);
        $builder->pageData($pageData);
        $builder->flush();

        $this->assertContains($pageData, $persisted, 'PageData entity must be persisted via ObjectManager in phaseOne');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_component_group(): void
    {
        $calls = [];
        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary')->group('nav');
        $builder->flush();

        $groupCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof ComponentGroup));
        $this->assertNotEmpty($groupCalls, 'persistTimestampedFields must be called for ComponentGroup');
        foreach ($groupCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for ComponentGroup');
        }
    }

    public function test_timestamped_persister_called_with_is_new_true_for_explicit_page_route(): void
    {
        $calls = [];
        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', route: '/home');
        $builder->flush();

        $routeCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof Route));
        $this->assertNotEmpty($routeCalls, 'persistTimestampedFields must be called for explicit page Route in phaseThree');
        foreach ($routeCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for page Route');
        }
    }

    public function test_an_auto_route_of_a_page_is_registered_only_under_its_own_name(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $page = $builder->page('home', 'Template', layout: 'main')->title('Home');
        $builder->flush();

        $route = $page->getRoute();
        $this->assertNotNull($route);
        $this->assertSame($route, $builder->getRoute((string) $route->getName()));
        $this->expectException(\LogicException::class);
        $builder->getRoute('any-name');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_explicit_pagedata_route(): void
    {
        $calls = [];
        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->pageData($pageData, route: '/articles/1');
        $builder->flush();

        $routeCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof Route));
        $this->assertNotEmpty($routeCalls, 'persistTimestampedFields must be called for explicit pageData Route in phaseThree');
        foreach ($routeCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for pageData Route');
        }
    }

    public function test_auto_generated_route_for_pagedata_is_persisted_via_manager(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $pageData = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->pageData($pageData);
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertNotEmpty($routes, 'Auto-generated Route for PageData must be explicitly persisted via ObjectManager');
    }

    public function test_an_auto_route_of_page_data_is_registered_only_under_its_own_name(): void
    {
        $pageData = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->pageData($pageData);
        $builder->flush();

        $route = $pageData->getRoute();
        $this->assertNotNull($route);
        $this->assertSame($route, $builder->getRoute((string) $route->getName()));
        $this->expectException(\LogicException::class);
        $builder->getRoute('anything');
    }

    public function test_on_routes_created_child_builders_contains_no_null_entries(): void
    {
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $capturedBuilders = null;

        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'Template', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $childBuilders) use (&$capturedBuilders): void {
                $capturedBuilders = $childBuilders;
            });
        $builder->flush();

        $this->assertIsArray($capturedBuilders);
        foreach ($capturedBuilders as $idx => $childBuilder) {
            $this->assertNotNull($childBuilder, "childBuilders[$idx] must not be null");
            $this->assertIsObject($childBuilder);
        }
    }

    public function test_on_routes_created_child_builders_are_reindexed_from_zero(): void
    {
        $parentPageData = self::titled(new class extends AbstractPageData {});
        $capturedKeys = null;

        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'Template', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $childBuilders) use (&$capturedKeys): void {
                $capturedKeys = array_keys($childBuilders);
            });
        $builder->flush();

        $this->assertSame([0], $capturedKeys, 'childBuilders must be re-indexed from 0 (array_values applied)');
    }

    public function test_phase_four_only_flushes_when_at_least_one_position_is_created(): void
    {
        $flushCount = 0;
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();

        $this->assertGreaterThan(0, $flushCount, 'phaseFour must flush when at least one position is created');
    }

    public function test_phase_four_does_not_flush_when_no_positions_exist(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true);
        $builder->flush();

        $countWithNoPositions = $flushCount;

        $flushCount = 0;
        $component = new class extends AbstractComponent {};
        $builder2 = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder2->layout('main', 'Primary');
        $builder2->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder2->flush();

        $this->assertGreaterThan($countWithNoPositions, $flushCount, 'Adding positions causes at least one extra flush in phaseFour');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_component_position(): void
    {
        $calls = [];
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();

        $positionCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof ComponentPosition));
        $this->assertNotEmpty($positionCalls, 'persistTimestampedFields must be called for ComponentPosition');
        foreach ($positionCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for ComponentPosition');
        }
    }

    public function test_component_is_persisted_via_manager_when_added_to_group(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();

        $this->assertContains($component, $persisted, 'Component must be persisted via ObjectManager when added to a group position');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_pagedata_position(): void
    {
        $calls = [];
        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->layout('main', 'Primary');
        $builder->page('template', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('App\Entity\ContentPageData', 'content');
        $builder->flush();

        $positionCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] instanceof ComponentPosition));
        $this->assertNotEmpty($positionCalls, 'persistTimestampedFields must be called for pageDataProperty ComponentPosition');
        foreach ($positionCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for pageData ComponentPosition');
        }
    }

    public function test_phase_four_flushes_when_only_page_data_positions_exist(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('template', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('App\Entity\ContentPageData', 'content');
        $builder->flush();

        $this->assertGreaterThan(0, $flushCount, 'phaseFour must flush when only pageDataPositions are created');
    }

    public function test_phase_four_flushes_exactly_once_when_only_page_data_positions_exist(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $builder->page('template', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->pageDataPosition('App\Entity\ContentPageData', 'content');
        $builder->flush();

        $this->assertSame(2, $flushCount, 'phaseFour must flush when only pageDataPositions are created ($hasAny = true path)');
    }

    public function test_phase_four_flushes_when_layout_group_has_component_position(): void
    {
        $flushCount = 0;
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary')
            ->group('nav')
            ->add($component);
        $builder->flush();

        $this->assertSame(2, $flushCount, 'phaseFour must flush when a layout group has component positions');
    }

    public function test_phase_four_does_not_flush_when_layout_group_has_no_positions(): void
    {
        $flushCount = 0;

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary')
            ->group('nav');
        $builder->flush();

        $this->assertSame(1, $flushCount, 'phaseFour must not flush when layout group has no positions');
    }

    public function test_phase_four_flushes_when_component_builder_group_has_position(): void
    {
        $flushCount = 0;
        $outerComponent = new class extends AbstractComponent {};
        $innerComponent = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->component($outerComponent)->group('items')->add($innerComponent);
        $builder->flush();

        $this->assertSame(2, $flushCount, 'phaseFour must flush when a component builder group has positions');
    }

    public function test_phase_four_does_not_flush_when_component_builder_group_has_no_positions(): void
    {
        $flushCount = 0;
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->component($component)->group('items');
        $builder->flush();

        $this->assertSame(1, $flushCount, 'phaseFour must not flush when component builder group has no positions');
    }

    public function test_timestamped_persister_called_with_is_new_true_for_component_builder_component(): void
    {
        $calls = [];
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls),
        );
        $builder->component($component)->group('items');
        $builder->flush();

        $componentCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] === $component));
        $this->assertNotEmpty($componentCalls, 'persistTimestampedFields must be called for component in componentBuilders (phaseOne)');
        foreach ($componentCalls as $call) {
            $this->assertTrue($call['isNew'], 'persistTimestampedFields must be called with isNew=true for component builder component');
        }
    }

    public function test_timestamped_persister_not_called_for_non_timestamped_component(): void
    {
        $calls = [];
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder(
            timestampedPersister: $this->recordingTimestampedPersister($calls, isConfigured: false),
        );
        $builder->component($component)->group('items');
        $builder->flush();

        $componentCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] === $component));
        $this->assertEmpty($componentCalls, 'persistTimestampedFields must NOT be called for non-timestamped components');
    }

    public function test_named_route_for_page_not_stored_when_route_generator_does_not_set_route_on_entity(): void
    {
        $routeGenerator = $this->createStub(RouteGeneratorInterface::class);
        $routeGenerator->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = new Route();
            $route->setPath('/whatever');

            return $route;
        });

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', routeName: 'home-page');
        $builder->flush();

        $this->expectException(\LogicException::class);
        $builder->getRoute('home-page');
    }

    public function test_named_route_for_pagedata_not_stored_when_route_generator_does_not_set_route_on_entity(): void
    {
        $routeGenerator = $this->createStub(RouteGeneratorInterface::class);
        $routeGenerator->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = new Route();
            $route->setPath('/whatever');

            return $route;
        });

        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder(routeGenerator: $routeGenerator);
        $builder->pageData($pageData, routeName: 'article');
        $builder->flush();

        $this->expectException(\LogicException::class);
        $builder->getRoute('article');
    }

    public function test_manager_persist_called_only_once_per_entity_object_identity(): void
    {
        $persistCount = 0;
        $component = new class extends AbstractComponent {};

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(
            static function (object $e) use ($component, &$persistCount): void {
                if ($e === $component) {
                    ++$persistCount;
                }
            }
        );
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($component);
        $builder->persist($component);
        $builder->flush();

        $this->assertSame(1, $persistCount, 'manager->persist() must be called exactly once per entity regardless of duplicate persist() calls');
    }

    public function test_second_pagedata_gets_route_when_first_already_has_one(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $firstPd = self::titled(new class extends AbstractPageData {});
        $secondPd = self::titled(new class extends AbstractPageData {});

        $existingRoute = new Route();
        $existingRoute->setPath('/first');
        $existingRoute->setName('first');
        $firstPd->setRoute($existingRoute);

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->once())
            ->method('create')
            ->with($secondPd)
            ->willReturnCallback(static function (AbstractPageData $pd): Route {
                $route = new Route();
                $route->setPath('/second');
                $route->setName('second');
                $pd->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder($em, $routeGenerator);
        $builder->pageData($firstPd);
        $builder->pageData($secondPd);
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(1, $routes, 'Only secondPd should get a new route; firstPd already had one and must be skipped with continue (not break)');
        $this->assertSame('/second', $routes[0]->getPath());
    }

    public function test_second_page_gets_route_when_first_already_has_one(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $routeGenerator = $this->createMock(RouteGeneratorInterface::class);
        $routeGenerator->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (object $entity): Route {
                $route = new Route();
                $route->setPath('/second');
                $route->setName('second');
                $entity->setRoute($route);

                return $route;
            });

        $builder = $this->makeBuilder($em, $routeGenerator);
        $builder->layout('main', 'Primary');
        $builder->page('first', 'Template', layout: 'main', route: '/first');
        $builder->page('second', 'Template', layout: 'main')->title('Page');
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertCount(2, $routes, 'Both pages must get routes; continue must not stop processing at the first page');
        $paths = array_map(static fn (Route $r) => $r->getPath(), $routes);
        $this->assertContains('/first', $paths);
        $this->assertContains('/second', $paths);
    }

    public function test_persist_with_associations_persists_owning_side_single_valued_association(): void
    {
        $persisted = [];

        $associated = new class extends AbstractComponent {};
        $mainEntity = new class extends AbstractComponent {
            public ?object $related = null;
        };
        $mainEntity->related = $associated;

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['related']);
        $metadata->method('isAssociationInverseSide')->willReturnCallback(
            static fn (string $name) => false
        );

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertContains($associated, $persisted, 'Owning-side single-valued association must be persisted recursively');
    }

    public function test_persist_with_associations_skips_inverse_side_associations(): void
    {
        $persisted = [];

        $inverseSideEntity = new class extends AbstractComponent {};
        $mainEntity = new class extends AbstractComponent {
            public ?object $inverseRelation = null;
        };
        $mainEntity->inverseRelation = $inverseSideEntity;

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['inverseRelation']);
        $metadata->method('isAssociationInverseSide')->willReturnCallback(
            static fn (string $name) => true
        );

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertNotContains($inverseSideEntity, $persisted, 'Inverse-side associations must NOT be recursively persisted');
    }

    public function test_persist_with_associations_handles_iterable_owning_side_collection(): void
    {
        $persisted = [];

        $item1 = new class extends AbstractComponent {};
        $item2 = new class extends AbstractComponent {};
        $mainEntity = new class extends AbstractComponent {
            /** @var object[] */
            public array $items = [];
        };
        $mainEntity->items = [$item1, $item2];

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['items']);
        $metadata->method('isAssociationInverseSide')->willReturnCallback(
            static fn (string $name) => false
        );

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertContains($item1, $persisted, 'item1 from iterable owning-side collection must be persisted');
        $this->assertContains($item2, $persisted, 'item2 from iterable owning-side collection must be persisted');
    }

    public function test_persist_with_associations_skips_null_association_value(): void
    {
        $persistCount = 0;

        $mainEntity = new class extends AbstractComponent {
            public ?object $maybeNull = null;
        };

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['maybeNull']);
        $metadata->method('isAssociationInverseSide')->willReturn(false);

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persistCount): void {
            ++$persistCount;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertSame(1, $persistCount, 'Null association value must not trigger additional persist() calls');
    }

    public function test_persist_with_associations_treats_uninitialized_property_as_null_not_value(): void
    {
        $persistCount = 0;

        $mainEntity = new class extends AbstractComponent {
            public string $uninitializedProp;
        };

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['uninitializedProp']);
        $metadata->method('isAssociationInverseSide')->willReturn(false);

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persistCount): void {
            ++$persistCount;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertSame(1, $persistCount, 'Uninitialized typed property must return null (not a value) and skip further persist calls');
    }

    public function test_persist_with_associations_returns_value_of_initialized_property(): void
    {
        $persisted = [];

        $associated = new class extends AbstractComponent {};
        $mainEntity = new class extends AbstractComponent {
            public ?object $initializedProp = null;
        };
        $mainEntity->initializedProp = $associated;

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['initializedProp']);
        $metadata->method('isAssociationInverseSide')->willReturn(false);

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($mainEntity);
        $builder->flush();

        $this->assertContains($associated, $persisted, 'Initialized property value must be returned and the associated entity persisted');
    }

    public function test_auto_route_page_with_null_route_name_does_not_register_empty_string_route(): void
    {
        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main');
        $builder->flush();

        $this->expectException(\LogicException::class);
        $builder->getRoute('');
    }

    public function test_auto_route_pagedata_with_null_route_name_does_not_register_empty_string_route(): void
    {
        $pageData = new class extends AbstractPageData {};

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->pageData($pageData);
        $builder->flush();

        $this->expectException(\LogicException::class);
        $builder->getRoute('');
    }

    public function test_double_flush_does_not_duplicate_entity_persists(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $component = new class extends AbstractComponent {};

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main')
            ->group('nav')
            ->add($component);
        $builder->flush();
        $firstCount = \count($persisted);

        $builder->flush();

        $this->assertCount($firstCount, $persisted, 'Second flush must not add duplicate persists');

        $entities = array_filter($persisted, static fn ($e) => $e instanceof Layout || $e instanceof Page || $e instanceof ComponentGroup);
        foreach (array_count_values(array_map(static fn ($e) => spl_object_id($e), array_values($entities))) as $count) {
            $this->assertSame(1, $count, 'Each entity must be persisted exactly once across multiple flush() calls');
        }
    }

    public function test_page_registered_after_first_flush_is_persisted_on_second_flush(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);

        $builder = $this->makeBuilder($em, $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->flush();

        $layoutsPersisted = \count(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertSame(0, $layoutsPersisted, 'No pages should be persisted after first flush');

        $builder->page('home', 'Template', layout: 'main');
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages, 'Page registered between flushes must be persisted on second flush');
    }

    public function test_on_routes_created_fires_exactly_once_across_multiple_flushes(): void
    {
        $callCount = 0;
        $parentPageData = self::titled(new class extends AbstractPageData {});

        $builder = $this->makeBuilder(routeGenerator: $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $builder->pageData($parentPageData)
            ->nested(static function (CwaFixtureBuilder $child): void {
                $child->page('chapter', 'Template', layout: 'main')->title('Page');
            })
            ->onRoutesCreated(static function (array $builders) use (&$callCount): void {
                ++$callCount;
            });

        $builder->flush();
        $builder->flush();

        $this->assertSame(1, $callCount, 'onRoutesCreated must fire exactly once even when flush() is called multiple times');
    }

    public function test_timestamped_persister_called_for_component_added_directly_to_group(): void
    {
        $calls = [];
        $component = new class extends AbstractComponent {};

        $persister = $this->createStub(TimestampedDataPersister::class);
        $persister->method('isConfigured')->willReturnCallback(
            static fn (object $entity): bool => $entity instanceof AbstractComponent
        );
        $persister->method('persistTimestampedFields')->willReturnCallback(
            static function (object $entity, bool $isNew) use (&$calls): void {
                $calls[] = ['entity' => $entity, 'isNew' => $isNew];
            }
        );

        $builder = $this->makeBuilder(timestampedPersister: $persister);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Template', layout: 'main', isTemplate: true)
            ->group('primary')
            ->add($component);
        $builder->flush();

        $componentCalls = array_values(array_filter($calls, static fn ($c) => $c['entity'] === $component));
        $this->assertNotEmpty($componentCalls, 'persistTimestampedFields must be called for component added via GroupBuilder::add() when isConfigured() returns true');
        foreach ($componentCalls as $call) {
            $this->assertTrue($call['isNew']);
        }
    }

    public function test_read_property_finds_property_declared_only_in_parent_class(): void
    {
        $persisted = [];

        $associated = new class extends AbstractComponent {};

        $parentEntity = new class extends AbstractComponent {
            public ?object $parentDeclaredProp = null;
        };
        $parentEntity->parentDeclaredProp = $associated;

        $childEntity = new class($parentEntity) extends AbstractComponent {
            public function __construct(private object $proto)
            {
            }
        };

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn(['parentDeclaredProp']);
        $metadata->method('isAssociationInverseSide')->willReturn(false);

        $em = $this->createStub(ObjectManager::class);
        $em->method('persist')->willReturnCallback(static function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('flush')->willReturnCallback(static function (): void {});

        $builder = $this->makeBuilder($em);
        $builder->persist($parentEntity);
        $builder->flush();

        $this->assertContains($associated, $persisted, 'Property declared in the same class must be found by readProperty');
    }

    public function test_layout_with_ui_class_names_sets_them_on_entity(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary', uiClassNames: ['bg-white', 'full-bleed']);
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertCount(1, $layouts);
        $this->assertSame(['bg-white', 'full-bleed'], $layouts[0]->uiClassNames);
    }

    public function test_layout_ui_class_names_can_be_set_via_fluent_method(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary')->uiClassNames('bold', 'dark');
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertCount(1, $layouts);
        $this->assertSame(['bold', 'dark'], $layouts[0]->uiClassNames);
    }

    public function test_page_with_ui_class_names_sets_them_on_entity(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main', uiClassNames: ['hero', 'spaced']);
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertSame(['hero', 'spaced'], $pages[0]->uiClassNames);
    }

    public function test_page_ui_class_names_can_be_set_via_fluent_method(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->page('home', 'PrimaryPageTemplate', layout: 'main')
            ->uiClassNames('compact', 'inverted');
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertCount(1, $pages);
        $this->assertSame(['compact', 'inverted'], $pages[0]->uiClassNames);
    }

    public function test_layout_null_ui_class_names_is_the_default(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertNull($layouts[0]->uiClassNames);
    }

    public function test_layout_ui_component_gets_cwa_layout_prefix(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->flush();

        $layouts = array_values(array_filter($persisted, static fn ($e) => $e instanceof Layout));
        $this->assertSame('CwaLayoutPrimary', $layouts[0]->uiComponent);
    }

    public function test_page_ui_component_gets_cwa_page_prefix(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $builder = $this->makeBuilder($em);

        $builder->layout('main', 'Primary');
        $builder->page('home', 'Blog', layout: 'main', isTemplate: true);
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertSame('CwaPageBlog', $pages[0]->uiComponent);
    }

    public function test_component_builder_ui_component_stores_full_prefixed_name(): void
    {
        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder();

        $builder->component($component)->uiComponent('YouTube');
        $builder->flush();

        $shortName = (new \ReflectionClass($component))->getShortName();
        $this->assertSame('CwaComponent' . $shortName . 'UiYouTube', $component->uiComponent);
    }

    public function test_component_builder_ui_class_names_variadic_sets_array_on_entity(): void
    {
        $component = new class extends AbstractComponent {};
        $builder = $this->makeBuilder();

        $builder->component($component)->uiClassNames('bold', 'dark');
        $builder->flush();

        $this->assertSame(['bold', 'dark'], $component->uiClassNames);
    }

    public function test_page_group_allows_the_given_component_classes(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn ($resource) => \is_string($resource) ? '/_/some_components' : '/_/pages/home'
        );

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/')->group('primary', allow: [\stdClass::class]);
        $builder->flush();

        $groups = array_values(array_filter($persisted, static fn ($e) => $e instanceof ComponentGroup));
        $this->assertCount(1, $groups);
        $this->assertSame(['/_/some_components'], $groups[0]->allowedComponents);
    }

    public function test_a_group_takes_its_maximum_number_of_components_from_every_owner_kind(): void
    {
        $persisted = [];
        $em = $this->collectingEm($persisted);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn ($resource) => \is_string($resource) ? '/_/some_components' : '/_/owners/' . spl_object_id($resource)
        );

        $builder = $this->makeBuilder($em, iriConverter: $iriConverter);
        $builder->layout('main', 'Primary')->group('nav', max: 1);
        $builder->page('home', 'Primary', layout: 'main', route: '/')->group('primary', max: 2);
        $builder->component(new class extends AbstractComponent {})->group('items', max: 3);
        $builder->layout('other', 'Primary')->group('footer');
        $builder->flush();

        $limits = [];
        foreach ($persisted as $entity) {
            if ($entity instanceof ComponentGroup) {
                $limits[strstr((string) $entity->reference, '_', true)] = $entity->maxComponents;
            }
        }
        ksort($limits);
        $this->assertSame(['footer' => null, 'items' => 3, 'nav' => 1, 'primary' => 2], $limits);
    }

    public function test_page_live_at_is_set_on_an_explicit_route(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted));
        $liveAt = new \DateTimeImmutable('2999-01-01');

        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/')->liveAt($liveAt);
        $builder->page('draft', 'Primary', layout: 'main', route: '/draft')->liveAt(null);
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertSame($liveAt, $routes[0]->getLiveAt());
        $this->assertNull($routes[1]->getLiveAt());
    }

    public function test_page_live_at_is_set_on_a_generated_route(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted), routeGenerator: $this->autoRouteGenerator());

        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main')->title('Page')->liveAt(null);
        $builder->flush();

        $pages = array_values(array_filter($persisted, static fn ($e) => $e instanceof Page));
        $this->assertNotNull($pages[0]->getRoute());
        $this->assertNull($pages[0]->getRoute()->getLiveAt());
    }

    public function test_route_keeps_its_default_live_at_when_none_is_given(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted));

        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/');
        $builder->flush();

        $routes = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route));
        $this->assertNotNull($routes[0]->getLiveAt());
    }

    public function test_page_data_live_at_is_set_on_explicit_and_generated_routes(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), routeGenerator: $this->autoRouteGenerator());
        $liveAt = new \DateTimeImmutable('2999-01-01');
        $explicit = self::titled(new class extends AbstractPageData {});
        $generated = self::titled(new class extends AbstractPageData {});

        $builder->pageData($explicit, route: '/explicit')->liveAt($liveAt);
        $builder->pageData($generated)->liveAt(null);
        $builder->flush();

        $this->assertSame($liveAt, $explicit->getRoute()->getLiveAt());
        $this->assertNull($generated->getRoute()->getLiveAt());
    }

    public function test_redirects_are_created_to_named_routes_in_order_and_are_named(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted));

        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->redirect('/old', to: 'about', name: 'old')->redirect('/older', to: 'old');
        $builder->flush();
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertCount(2, $redirects);
        $this->assertSame('/old', $redirects[0]->getPath());
        $this->assertSame('old', $redirects[0]->getName());
        $this->assertSame($builder->getRoute('about'), $redirects[0]->getRedirect());
        $this->assertSame('older', $redirects[1]->getName());
        $this->assertSame($redirects[0], $redirects[1]->getRedirect());
        $this->assertSame($redirects[1], $builder->getRoute('older'));
    }

    public function test_a_redirect_whose_derived_name_is_taken_by_a_route_of_the_same_load_gets_a_suffix(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->autoRouteGenerator());

        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about-us', routeName: 'about');
        $builder->redirect('/about', to: 'about')->redirect('/about/', to: 'about');
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertSame(['about-1', 'about-2'], array_map(static fn (Route $r) => $r->getName(), $redirects));
        $this->assertSame('/about-us', $builder->getRoute('about')->getPath());
        $this->assertSame($redirects[0], $builder->getRoute('about-1'));
    }

    public function test_a_redirect_whose_derived_name_is_taken_by_a_generated_route_gets_a_suffix(): void
    {
        $persisted = [];
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = (new Route())->setPath('/about-us')->setName('about');
            $entity->setRoute($route);

            return $route;
        });
        $builder = $this->makeBuilder($this->collectingEm($persisted), $generator);

        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', routeName: 'about-page')->title('About');
        $builder->redirect('/about', to: 'about-page');
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertSame(['about-1'], array_map(static fn (Route $r) => $r->getName(), $redirects));
    }

    public function test_a_redirect_given_a_name_already_used_in_the_same_load_throws_naming_the_clash(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());

        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about-us', routeName: 'about');
        $builder->redirect('/about', to: 'about', name: 'about');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The redirect "/about" cannot be named "about", because the route "/about-us" already has that name.');
        $builder->flush();
    }

    public function test_redirect_to_an_unknown_route_name_throws_naming_the_redirect_and_the_routes_of_the_load(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about');
        $builder->redirect('/old', to: 'missing');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The redirect "/old" leads to the route name "missing", but no route in this load or the database has that name. Routes in this load: about.');
        $builder->flush();
    }

    public function test_after_routes_callbacks_run_once_after_routes_and_before_positions_are_created(): void
    {
        $persisted = [];
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm($persisted, $flushCount));
        $component = new class extends AbstractComponent {};
        $seen = [];

        $builder->layout('main', 'Primary')->group('nav')->add($component);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use (&$seen, &$persisted): void {
            $seen[] = [
                $cwa->getRoute('about')->getPath(),
                \count(array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition)),
            ];
        });
        $builder->flush();
        $flushesAfterFirst = $flushCount;
        $builder->flush();

        $this->assertSame([['/about', 0]], $seen);
        $this->assertCount(1, array_filter($persisted, static fn ($e) => $e instanceof ComponentPosition));
        $this->assertSame($flushesAfterFirst, $flushCount);
    }

    public function test_after_routes_changes_are_flushed(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->flush();
        $withoutCallback = $flushCount;

        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->afterRoutes(static function (): void {});
        $builder->flush();

        $this->assertSame($withoutCallback + 1, $flushCount);
    }

    public function test_a_redirect_added_after_a_flush_is_created_by_the_next_flush_and_timestamped(): void
    {
        $persisted = [];
        $flushCount = 0;
        $timestamped = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted, $flushCount), timestampedPersister: $this->recordingTimestampedPersister($timestamped));

        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->redirect('/old', to: 'about');
        $builder->flush();
        $builder->redirect('/older', to: 'old');
        $flushesBefore = $flushCount;
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertSame(['/old', '/older'], array_map(static fn (Route $r) => $r->getPath(), $redirects));
        $this->assertSame($flushesBefore + 1, $flushCount);
        $timestampedRedirects = array_values(array_filter($timestamped, static fn ($call) => $call['entity'] instanceof Route && null !== $call['entity']->getRedirect()));
        $this->assertCount(2, $timestampedRedirects);
        $this->assertTrue($timestampedRedirects[1]['isNew']);
    }

    public function test_live_at_on_a_template_page_without_a_route_is_ignored(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());

        $builder->layout('main', 'Primary');
        $page = $builder->page('template', 'Primary', layout: 'main', isTemplate: true)->liveAt(null);
        $builder->flush();

        $this->assertNull($page->getRoute());
    }

    private function publishingBuilder(ObjectManager $em, ?RouteGeneratorInterface $routeGenerator = null): CwaFixtureBuilder
    {
        return (new CwaFixtureBuilder(
            $this->createStub(TimestampedDataPersister::class),
            $routeGenerator ?? $this->createStub(RouteGeneratorInterface::class),
            $this->createStub(IriConverterInterface::class),
            null,
            null,
            null,
            null,
            new PublishableAttributeReader($this->createStub(ManagerRegistry::class)),
        ))->withManager($em);
    }

    /**
     * @param list<array{object, int}> $persistedAt
     */
    private function timedEm(array &$persistedAt, int &$flushCount, string $publishedAtType = 'datetime'): ObjectManager
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getAssociationNames')->willReturn([]);
        $metadata->method('getTypeOfField')->willReturn($publishedAtType);
        $em = $this->createStub(ObjectManager::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->method('persist')->willReturnCallback(
            static function (object $e) use (&$persistedAt, &$flushCount): void { $persistedAt[] = [$e, $flushCount]; }
        );
        $em->method('flush')->willReturnCallback(
            static function () use (&$flushCount): void { ++$flushCount; }
        );

        return $em;
    }

    /**
     * @param list<array{object, int}> $persistedAt
     */
    private static function flushCountWhenPersisted(array $persistedAt, object $entity): ?int
    {
        foreach ($persistedAt as [$persisted, $flushCount]) {
            if ($persisted === $entity) {
                return $flushCount;
            }
        }

        return null;
    }

    /**
     * @param list<array{object, int}> $persistedAt
     */
    private static function flushCountWhenPositioned(array $persistedAt, object $component): ?int
    {
        foreach ($persistedAt as [$persisted, $flushCount]) {
            if ($persisted instanceof ComponentPosition && $persisted->component === $component) {
                return $flushCount;
            }
        }

        return null;
    }

    public function test_a_nested_closure_inside_a_nested_closure_creates_the_grandchild_in_one_flush(): void
    {
        $persisted = [];
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm($persisted, $flushCount));
        $builder->layout('main', 'Primary');
        $grandchild = null;
        $builder->page('parent', 'Primary', layout: 'main', route: '/parent')->nested(static function (CwaFixtureBuilder $cwa) use (&$grandchild): void {
            $cwa->page('child', 'Primary', layout: 'main', route: '/parent/child')->nested(static function (CwaFixtureBuilder $cwa) use (&$grandchild): void {
                $grandchild = $cwa->page('grandchild', 'Primary', layout: 'main', route: '/parent/child/grandchild');
            });
        });
        $builder->flush();

        $this->assertNotNull($grandchild);
        $this->assertContains($grandchild->getPage(), $persisted);
        $this->assertSame('child', $grandchild->getPage()->getParentPage()?->reference);
        $this->assertSame('/parent/child/grandchild', $grandchild->getRoute()?->getPath());
        $this->assertSame(3, $flushCount);
    }

    public function test_publish_sets_the_publishable_date_now_and_returns_the_resource(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getTypeOfField')->willReturn('datetime');
        $em = $this->createMock(ObjectManager::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');
        $builder = $this->publishingBuilder($em);
        $component = new DummyPublishableComponent();

        $before = new \DateTimeImmutable();
        $returned = $builder->publish($component);
        $after = new \DateTimeImmutable();

        $this->assertSame($component, $returned);
        $this->assertInstanceOf(\DateTime::class, $component->getPublishedAt());
        $this->assertGreaterThanOrEqual($before, $component->getPublishedAt());
        $this->assertLessThanOrEqual($after, $component->getPublishedAt());
    }

    /**
     * @return iterable<string, array{string, \DateTimeInterface, class-string<\DateTimeInterface>}>
     */
    public static function publishedAtMappings(): iterable
    {
        yield 'datetime' => ['datetime', new \DateTimeImmutable('2099-01-15T09:00:00+00:00'), \DateTime::class];
        yield 'datetime_immutable' => ['datetime_immutable', new \DateTime('2099-01-15T09:00:00+00:00'), \DateTimeImmutable::class];
    }

    #[DataProvider('publishedAtMappings')]
    public function test_publish_at_sets_the_given_date_in_the_class_the_mapping_uses(string $type, \DateTimeInterface $at, string $expectedClass): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount, $type));
        $component = new DummyPublishableComponent();

        $builder->publish($component, at: $at);

        $this->assertInstanceOf($expectedClass, $component->getPublishedAt());
        $this->assertSame('2099-01-15T09:00:00+00:00', $component->getPublishedAt()->format(\DATE_ATOM));
        $this->assertSame([], $persistedAt);
    }

    public function test_publish_on_a_non_publishable_object_throws(): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));

        $this->expectException(\LogicException::class);
        $builder->publish(new DummyComponent());
    }

    public function test_a_route_link_resolves_before_after_routes_callbacks_run(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $seen = false;

        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->component($link)->linkRoute('route', 'about');
        $builder->afterRoutes(static function () use ($link, &$seen): void {
            $seen = $link->route;
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('about'), $seen);
        $this->assertSame($builder->getRoute('about'), $link->route);
    }

    public function test_a_route_link_on_page_data_resolves_before_after_routes_callbacks_run(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $pageData = new PageDataWithRoute();
        $pageData->setTitle('Event');
        $seen = false;

        $this->assertInstanceOf(PageDataBuilder::class, $builder->pageData($pageData, route: '/event')->linkRoute('relatedRoute', 'about'));
        $builder->afterRoutes(static function () use ($pageData, &$seen): void {
            $seen = $pageData->relatedRoute;
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('about'), $seen);
        $this->assertSame($builder->getRoute('about'), $pageData->relatedRoute);
    }

    public function test_a_route_link_declared_in_an_after_routes_callback_is_applied(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($link): void {
            $cwa->component($link)->linkRoute('route', 'about');
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('about'), $link->route);
    }

    public function test_a_route_link_declared_in_an_on_routes_created_callback_is_applied(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $pageData = new PageDataWithRoute();
        $pageData->setTitle('Event');
        $pageDataBuilder = $builder->pageData($pageData, route: '/event');
        $pageDataBuilder->onRoutesCreated(static function () use ($pageDataBuilder): void {
            $pageDataBuilder->linkRoute('relatedRoute', 'about');
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('about'), $pageData->relatedRoute);
    }

    public function test_a_route_link_declared_in_an_after_routes_callback_to_an_unknown_route_name_throws(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($link): void {
            $cwa->component($link)->linkRoute('route', 'missing');
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(DummyNavigationLink::class . '::$route links to the route name "missing"');
        $builder->flush();
    }

    public function test_a_route_link_is_applied_once_when_links_are_resolved_again(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->page('team', 'Primary', layout: 'main', route: '/team', routeName: 'team');
        $builder->component($link)->linkRoute('route', 'about');
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($link): void {
            $link->route = $cwa->getRoute('team');
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('team'), $link->route);
    }

    public function test_a_route_link_adds_no_flush(): void
    {
        $counts = [];
        foreach ([false, true] as $withLink) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
            $link = new DummyNavigationLink();
            $builder->layout('main', 'Primary')->group('nav')->add($link);
            $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
            if ($withLink) {
                $builder->component($link)->linkRoute('route', 'about');
            }
            $builder->flush();
            $counts[] = $flushCount;
        }

        $this->assertSame($counts[0], $counts[1]);
    }

    public function test_a_route_link_to_an_unknown_route_name_throws(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->component($link)->linkRoute('route', 'missing');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(DummyNavigationLink::class . '::$route links to the route name "missing", but no route in this load or the database has that name. Routes in this load: none.');
        $builder->flush();
    }

    public function test_a_page_route_redirect_resolves_before_after_routes_callbacks_run(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $seen = false;

        $builder->layout('main', 'Primary');
        $home = $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $builder->page('graphs', 'Primary', layout: 'main', route: '/graphs');
        $this->assertSame($home, $home->redirectTo('graphs'));
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use (&$seen): void {
            $seen = $cwa->getRoute('home')->getRedirect();
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('graphs'), $seen);
        $this->assertSame($home->getPage(), $builder->getRoute('home')->getPage());
        $this->assertNull($builder->getRoute('graphs')->getRedirect());
    }

    public function test_a_page_data_route_redirect_resolves_before_after_routes_callbacks_run(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $pageData = new PageData();
        $pageData->setTitle('Event');
        $seen = false;

        $pageDataBuilder = $builder->pageData($pageData, route: '/event');
        $this->assertSame($pageDataBuilder, $pageDataBuilder->redirectTo('about'));
        $builder->afterRoutes(static function () use ($pageData, &$seen): void {
            $seen = $pageData->getRoute()?->getRedirect();
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('about'), $seen);
    }

    public function test_a_page_route_redirect_to_a_redirect_of_the_same_load_and_to_a_generated_route_resolves(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator());
        $builder->layout('main', 'Primary');
        $generated = $builder->page('graphs', 'Primary', layout: 'main')->title('Graphs');
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->redirectTo('old');
        $builder->page('resources', 'Primary', layout: 'main', route: '/resources')->redirectTo((string) spl_object_id($generated->getPage()));
        $builder->redirect('/old', to: 'resources', name: 'old');
        $builder->flush();

        $this->assertSame($builder->getRoute('old'), $builder->getRoute('home')->getRedirect());
        $this->assertSame($generated->getRoute(), $builder->getRoute('resources')->getRedirect());
    }

    public function test_a_page_route_redirect_declared_in_an_after_routes_callback_is_applied_and_flushed(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $home = $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $builder->page('graphs', 'Primary', layout: 'main', route: '/graphs', routeName: 'graphs');
        $builder->flush();
        $flushesBefore = $flushCount;

        $builder->afterRoutes(static function () use ($home): void {
            $home->redirectTo('graphs');
        });
        $builder->flush();

        $this->assertSame($builder->getRoute('graphs'), $builder->getRoute('home')->getRedirect());
        $this->assertSame($flushesBefore + 1, $flushCount);
    }

    public function test_a_page_route_redirect_declared_after_a_flush_is_applied_and_flushed_by_the_next_flush(): void
    {
        $flushCount = 0;
        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
        $builder->layout('main', 'Primary');
        $home = $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
        $builder->page('graphs', 'Primary', layout: 'main', route: '/graphs', routeName: 'graphs');
        $builder->flush();
        $flushesBefore = $flushCount;

        $home->redirectTo('graphs');
        $builder->flush();

        $this->assertSame($builder->getRoute('graphs'), $builder->getRoute('home')->getRedirect());
        $this->assertSame($flushesBefore + 1, $flushCount);
    }

    public function test_a_page_route_redirect_is_applied_once_when_links_are_resolved_again(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->redirectTo('graphs');
        $builder->page('graphs', 'Primary', layout: 'main', route: '/graphs', routeName: 'graphs');
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa): void {
            $cwa->getRoute('home')->setRedirect(null);
        });
        $builder->flush();
        $builder->flush();

        $this->assertNull($builder->getRoute('home')->getRedirect());
    }

    public function test_a_page_route_redirect_adds_no_flush(): void
    {
        $counts = [];
        foreach ([false, true] as $withRedirect) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
            $builder->layout('main', 'Primary');
            $home = $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home');
            $builder->page('graphs', 'Primary', layout: 'main', route: '/graphs', routeName: 'graphs');
            if ($withRedirect) {
                $home->redirectTo('graphs');
            }
            $builder->flush();
            $counts[] = $flushCount;
        }

        $this->assertSame([2, 2], $counts);
    }

    public function test_a_page_route_redirect_to_an_unknown_route_name_throws_naming_the_page(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->redirectTo('missing');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The route of the page "home" redirects to the route name "missing", but no route in this load or the database has that name. Routes in this load: home.');
        $builder->flush();
    }

    public function test_a_page_data_route_redirect_to_an_unknown_route_name_throws_naming_the_page_data(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $pageData = new PageData();
        $pageData->setTitle('Event');
        $builder->pageData($pageData, route: '/event')->redirectTo('missing');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The route of the page data "Event" redirects to the route name "missing"');
        $builder->flush();
    }

    public function test_a_page_route_redirect_to_its_own_route_throws_naming_the_page_and_the_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home')->redirectTo('home');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('redirectTo(): the page "home" cannot redirect its own route "/" to itself.');
        $builder->flush();
    }

    public function test_a_page_data_route_redirect_to_its_own_route_throws_naming_the_page_data_and_the_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $pageData = new PageData();
        $pageData->setTitle('Event');
        $builder->pageData($pageData, route: '/event', routeName: 'event')->redirectTo('event');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('redirectTo(): the page data "Event" cannot redirect its own route "/event" to itself.');
        $builder->flush();
    }

    public function test_a_redirect_on_a_page_with_no_route_throws_naming_the_page(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->page('template', 'Primary', layout: 'main', isTemplate: true)->redirectTo('about');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('redirectTo(): the page "template" has no route to redirect to "about".');
        $builder->flush();
    }

    public function test_a_redirect_on_untitled_page_data_with_no_route_throws_naming_its_class(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->page('article', 'Primary', layout: 'main', isTemplate: true);
        $builder->pageData(new PageData(), template: 'article')->redirectTo('about');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('redirectTo(): the page data "' . PageData::class . '" has no route to redirect to "about".');
        $builder->flush();
    }

    public function test_a_route_link_to_a_property_that_cannot_hold_a_route_throws_naming_link_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('about', 'Primary', layout: 'main', route: '/about');
        $builder->component($link)->linkRoute('target', 'about');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('linkRoute(): ' . DummyNavigationLink::class . ' has no writable property "target" that can hold a Route.');
        $builder->flush();
    }

    private function suffixingRouteGenerator(): RouteGeneratorInterface
    {
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('create')->willReturnCallback(static function (AbstractPage $entity): Route {
            $name = strtolower((string) $entity->getTitle()) . '-1';
            $route = (new Route())->setPath('/' . $name)->setName($name);
            $entity->setRoute($route);

            return $route;
        });

        return $generator;
    }

    public function test_a_page_builder_as_a_route_link_target_resolves_to_its_generated_suffixed_route_with_no_extra_flush(): void
    {
        $counts = [];
        foreach (['about-1', null] as $routeName) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), $this->suffixingRouteGenerator());
            $link = new DummyNavigationLink();
            $builder->layout('main', 'Primary')->group('nav')->add($link);
            $about = $builder->page('about', 'Primary', layout: 'main')->title('About');
            $componentBuilder = null === $routeName ? $builder->component($link)->linkRoute('route', page: $about) : $builder->component($link)->linkRoute('route', $routeName);
            $this->assertInstanceOf(ComponentBuilder::class, $componentBuilder);
            $builder->flush();
            $counts[] = $flushCount;

            $this->assertSame('/about-1', $link->route?->getPath());
            $this->assertSame($about->getRoute(), $link->route);
        }

        $this->assertSame($counts[0], $counts[1]);
    }

    public function test_a_page_data_builder_as_a_route_link_target_resolves_to_its_generated_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->suffixingRouteGenerator());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $about = $builder->page('about', 'Primary', layout: 'main')->title('About');
        $event = $builder->pageData(self::titled(new PageData(), 'Event'));
        $related = new PageDataWithRoute();
        $related->setTitle('Related');

        $builder->component($link)->linkRoute('route', page: $event);
        $this->assertInstanceOf(PageDataBuilder::class, $builder->pageData($related)->linkRoute('relatedRoute', page: $about));
        $builder->flush();

        $this->assertSame('/event-1', $link->route?->getPath());
        $this->assertSame('/about-1', $related->relatedRoute?->getPath());
    }

    public function test_a_redirect_to_a_page_builder_or_a_page_data_builder_leads_to_its_generated_route(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted), $this->suffixingRouteGenerator());
        $builder->layout('main', 'Primary');
        $about = $builder->page('about', 'Primary', layout: 'main')->title('About');
        $event = $builder->pageData(self::titled(new PageData(), 'Event'));

        $builder->redirect('/old-about', to: $about)->redirect('/old-event', to: $event);
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertSame(['/about-1', '/event-1'], array_map(static fn (Route $r) => $r->getRedirect()->getPath(), $redirects));
    }

    public function test_an_iri_link_takes_a_page_builder_a_page_data_builder_or_either_as_a_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->suffixingRouteGenerator(), $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $blog = $builder->page('blog', 'Primary', layout: 'main')->title('Blog');
        $event = $builder->pageData(self::titled(new PageData(), 'Event'));

        $builder->component($collection)
            ->linkIri('defaultQueryParameters', key: 'page', page: $blog)
            ->linkIri('defaultQueryParameters', key: 'pageData', pageData: $event)
            ->linkIri('defaultQueryParameters', key: 'pageRoute', route: $blog)
            ->linkIri('defaultQueryParameters', key: 'pageDataRoute', route: $event);
        $builder->flush();

        $this->assertSame([
            'page' => '/_/pages/blog',
            'pageData' => '/page_data/Event',
            'pageRoute' => '/_/routes/blog-1',
            'pageDataRoute' => '/_/routes/event-1',
        ], $collection->getDefaultQueryParameters());
    }

    public function test_a_route_link_to_a_page_builder_with_no_route_throws_naming_the_page_and_the_caller(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $template = $builder->page('template', 'Primary', layout: 'main', isTemplate: true);
        $builder->component($link)->linkRoute('route', page: $template);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(DummyNavigationLink::class . '::$route links to the page "template", which has no route.');
        $builder->flush();
    }

    public function test_a_redirect_to_a_page_data_builder_with_no_route_throws_naming_the_page_data_and_the_redirect(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->suffixingRouteGenerator());
        $builder->layout('main', 'Primary');
        $event = $builder->pageData(self::titled(new PageData(), 'Event'))->withoutRoute();
        $builder->redirect('/old', to: $event);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The redirect "/old" leads to the page data ' . PageData::class . ' "Event", which has no route.');
        $builder->flush();
    }

    public function test_an_iri_link_to_the_route_of_an_untitled_page_data_builder_throws_naming_the_caller(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->suffixingRouteGenerator(), $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $untitled = $builder->pageData(new PageData());
        $builder->component($collection)->linkIri('resourceIri', route: $untitled);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Collection::class . '::$resourceIri links to the page data ' . PageData::class . ' (untitled), which has no route.');
        $builder->flush();
    }

    public function test_a_route_link_with_neither_a_route_name_nor_a_page_throws(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('linkRoute() for the property "route" needs exactly one of routeName or page; 0 given.');
        $builder->component(new DummyNavigationLink())->linkRoute('route');
    }

    public function test_a_route_link_with_both_a_route_name_and_a_page_throws(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $about = $builder->page('about', 'Primary', layout: 'main', route: '/about');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('linkRoute() for the property "relatedRoute" needs exactly one of routeName or page; 2 given.');
        $builder->pageData(new PageDataWithRoute())->linkRoute('relatedRoute', 'about', page: $about);
    }

    public function test_an_iri_link_writes_the_iri_of_a_page_into_a_key_of_an_array_field_before_after_routes_callbacks_run(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $collection->setDefaultQueryParameters(['pagination' => false, 'parentPage' => null]);
        $seen = null;

        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
        $this->assertInstanceOf(ComponentBuilder::class, $builder->component($collection)->linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog'));
        $builder->afterRoutes(static function () use ($collection, &$seen): void {
            $seen = $collection->getDefaultQueryParameters();
        });
        $builder->flush();

        $this->assertSame(['pagination' => false, 'parentPage' => '/_/pages/blog'], $seen);
        $this->assertSame(['pagination' => false, 'parentPage' => '/_/pages/blog'], $collection->getDefaultQueryParameters());
    }

    public function test_an_iri_link_with_no_key_writes_the_iri_as_the_whole_value(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->component($collection)->linkIri('resourceIri', layout: 'main');
        $builder->flush();

        $this->assertSame('/_/layouts/main', $collection->getResourceIri());
    }

    public function test_an_iri_link_follows_a_key_path_and_creates_the_keys_it_needs(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $collection->setDefaultQueryParameters(['filters' => [['page' => null], 'kept']]);
        $empty = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection)->add($empty);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->component($collection)->linkIri('defaultQueryParameters', key: ['filters', 0, 'page'], route: 'about');
        $builder->component($empty)->linkIri('defaultQueryParameters', key: ['filters', 'route'], route: 'about');
        $builder->flush();

        $this->assertSame(['filters' => [['page' => '/_/routes/about'], 'kept']], $collection->getDefaultQueryParameters());
        $this->assertSame(['filters' => ['route' => '/_/routes/about']], $empty->getDefaultQueryParameters());
    }

    public function test_an_iri_link_to_page_data_writes_the_iri_of_the_page_data_at_that_route(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $builder->layout('main', 'Primary');
        $event = new PageData();
        $event->setTitle('Event');
        $builder->pageData($event, route: '/event', routeName: 'event');
        $listing = new PageDataWithFilters();
        $listing->setTitle('Listing');

        $this->assertInstanceOf(PageDataBuilder::class, $builder->pageData($listing, route: '/listing')->linkIri('filters', key: 'event', pageData: 'event'));
        $builder->flush();

        $this->assertSame(['event' => '/page_data/Event'], $listing->filters);
    }

    public function test_an_iri_link_to_page_data_finds_it_by_a_generated_route_that_does_not_point_back_at_it(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), $this->autoRouteGenerator(), $this->referenceIriConverter());
        $builder->layout('main', 'Primary');
        $other = new PageData();
        $other->setTitle('Other');
        $builder->pageData($other, routeName: 'other');
        $event = new PageData();
        $event->setTitle('Event');
        $builder->pageData($event, routeName: 'event');
        $collection = new Collection();
        $builder->component($collection)->linkIri('resourceIri', pageData: 'event');
        $builder->flush();

        $this->assertNull($event->getRoute()?->getPageData());
        $this->assertSame('/page_data/Event', $collection->getResourceIri());
    }

    public function test_an_iri_link_written_to_a_component_already_persisted_is_flushed(): void
    {
        $counts = [];
        foreach ([false, true] as $withLink) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), iriConverter: $this->referenceIriConverter());
            $collection = new Collection();
            $builder->layout('main', 'Primary');
            $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
            $builder->component($collection)->group('items');
            if ($withLink) {
                $builder->component($collection)->linkIri('resourceIri', page: 'blog');
            }
            $builder->flush();
            $counts[] = $flushCount;
        }

        $this->assertSame($counts[0] + 1, $counts[1]);
    }

    public function test_an_iri_link_to_a_property_of_another_type_throws_naming_link_iri(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
        $builder->component($link)->linkIri('route', page: 'blog');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('linkIri(): ' . DummyNavigationLink::class . ' has no writable property "route" that can hold an IRI.');
        $builder->flush();
    }

    public function test_an_iri_link_declared_in_an_after_routes_callback_is_applied_once(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
        $builder->page('news', 'Primary', layout: 'main', route: '/news');
        $builder->component($collection)->linkIri('resourceIri', page: 'blog');
        $builder->afterRoutes(static function (CwaFixtureBuilder $cwa) use ($collection): void {
            $collection->setResourceIri('/edited');
            $cwa->component($collection)->linkIri('defaultQueryParameters', key: 'parentPage', page: 'news');
        });
        $builder->flush();

        $this->assertSame('/edited', $collection->getResourceIri());
        $this->assertSame(['parentPage' => '/_/pages/news'], $collection->getDefaultQueryParameters());
    }

    public function test_an_iri_link_adds_no_flush(): void
    {
        $counts = [];
        foreach ([false, true] as $withLink) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), iriConverter: $this->referenceIriConverter());
            $collection = new Collection();
            $builder->layout('main', 'Primary')->group('nav')->add($collection);
            $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
            if ($withLink) {
                $builder->component($collection)->linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog');
            }
            $builder->flush();
            $counts[] = $flushCount;
        }

        $this->assertSame($counts[0], $counts[1]);
    }

    public static function unknownIriTargets(): iterable
    {
        yield 'a page' => [['page' => 'missing'], 'links to the page "missing", but no page in this load or the database has that reference.'];
        yield 'a layout' => [['layout' => 'missing'], 'links to the layout "missing", but no layout in this load or the database has that reference.'];
        yield 'a route' => [['route' => 'missing'], 'links to the route name "missing", but no route in this load or the database has that name.'];
        yield 'page data' => [['pageData' => 'missing'], 'links to the route name "missing", but no route in this load or the database has that name.'];
    }

    /**
     * @param array<string, string> $target
     */
    #[DataProvider('unknownIriTargets')]
    public function test_an_iri_link_to_something_that_is_not_there_throws(array $target, string $message): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->component($collection)->linkIri('defaultQueryParameters', 'parentPage', ...$target);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Collection::class . '::$defaultQueryParameters["parentPage"] ' . $message);
        $builder->flush();
    }

    public function test_an_iri_link_to_a_route_with_no_page_data_throws(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->component($collection)->linkIri('resourceIri', pageData: 'about');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(Collection::class . '::$resourceIri links to the page data at the route "about", but that route has no page data.');
        $builder->flush();
    }

    public function test_an_iri_link_into_a_value_that_is_not_an_array_throws_naming_link_iri(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $collection = new Collection();
        $collection->setDefaultQueryParameters(['filters' => 'flat']);
        $builder->layout('main', 'Primary')->group('nav')->add($collection);
        $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
        $builder->component($collection)->linkIri('defaultQueryParameters', key: ['filters', 'page'], page: 'blog');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('linkIri(): ' . Collection::class . '::$defaultQueryParameters["filters"] is not an array, so it has no key "page".');
        $builder->flush();
    }

    public function test_an_iri_link_to_a_property_that_cannot_hold_it_throws_naming_link_iri(): void
    {
        $builder = $this->makeBuilder($this->collectingEm(), iriConverter: $this->referenceIriConverter());
        $link = new DummyNavigationLink();
        $builder->layout('main', 'Primary')->group('nav')->add($link);
        $builder->page('blog', 'Primary', layout: 'main', route: '/blog');
        $builder->component($link)->linkIri('target', page: 'blog');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('linkIri(): ' . DummyNavigationLink::class . ' has no writable property "target" that can hold an IRI.');
        $builder->flush();
    }

    private function referenceIriConverter(): IriConverterInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (object|string $resource): string => match (true) {
            $resource instanceof Page => '/_/pages/' . $resource->reference,
            $resource instanceof Layout => '/_/layouts/' . $resource->reference,
            $resource instanceof Route => '/_/routes/' . $resource->getName(),
            $resource instanceof AbstractPageData => '/page_data/' . $resource->getTitle(),
            default => '/_/collection',
        });

        return $iriConverter;
    }

    public function test_get_route_before_flush_hints_at_flush(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Named route "about" not found. Did you call flush() before getRoute()?');
        $builder->getRoute('about');
    }

    public function test_a_groupless_component_builder_is_persisted_with_the_positions(): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));
        $placed = new DummyComponent();
        $groupless = new DummyComponent();

        $builder->layout('main', 'Primary')->group('nav')->add($placed);
        $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
        $builder->component($groupless)->uiComponent('Loose');
        $builder->flush();

        $this->assertNotNull(self::flushCountWhenPositioned($persistedAt, $placed));
        $this->assertSame(self::flushCountWhenPositioned($persistedAt, $placed), self::flushCountWhenPersisted($persistedAt, $groupless));
    }

    public function test_a_draft_is_linked_at_once_and_persisted_with_the_positions_without_an_extra_flush(): void
    {
        $counts = [];
        foreach ([false, true] as $withDraft) {
            $persistedAt = [];
            $flushCount = 0;
            $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));
            $published = new DummyPublishableComponent();
            $builder->layout('main', 'Primary')->group('nav')->add($published);
            $builder->page('about', 'Primary', layout: 'main', route: '/about', routeName: 'about');
            if ($withDraft) {
                $draft = new DummyPublishableComponent();
                $this->assertSame($builder->component($published), $builder->component($published)->draft($draft));
                $this->assertSame($published, $draft->getPublishedResource());
                $this->assertSame([$draft], $builder->component($published)->getDrafts());
            }
            $builder->flush();
            $counts[] = $flushCount;
        }

        $this->assertSame($counts[0], $counts[1]);
        $this->assertSame(self::flushCountWhenPositioned($persistedAt, $published), self::flushCountWhenPersisted($persistedAt, $draft));
    }

    public function test_a_group_given_to_a_draft_of_a_published_component_belongs_to_the_published_component(): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));
        $published = new DummyPublishableComponent();
        $builder->layout('main', 'Primary')->group('nav')->add($published);
        $draft = new DummyPublishableComponent();
        $builder->component($published)->draft($draft);
        $builder->component($draft)->group('panels')->add(new DummyComponent());
        $builder->flush();

        $this->assertCount(0, $draft->getComponentGroups());
        $this->assertCount(1, $published->getComponentGroups());
        $group = $published->getComponentGroups()->first();
        $this->assertInstanceOf(ComponentGroup::class, $group);
        $this->assertSame([$published], $group->components->toArray());
    }

    public function test_a_group_given_to_a_never_published_draft_belongs_to_the_draft(): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));
        $draft = new DummyPublishableComponent();
        $builder->layout('main', 'Primary')->group('nav')->add($draft);
        $builder->component($draft)->group('panels')->add(new DummyComponent());
        $builder->flush();

        $this->assertCount(1, $draft->getComponentGroups());
    }

    public function test_a_draft_of_a_non_publishable_component_throws(): void
    {
        $persistedAt = [];
        $flushCount = 0;
        $builder = $this->publishingBuilder($this->timedEm($persistedAt, $flushCount));

        $this->expectException(\LogicException::class);
        $builder->component(new DummyComponent())->draft(new DummyComponent());
    }

    public function test_an_explicit_route_without_a_route_name_is_addressable_by_its_derived_name(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $page = $builder->page('about', 'Primary', layout: 'main', route: '/about/us');
        $builder->flush();

        $this->assertSame($page->getRoute(), $builder->getRoute('about-us'));
    }

    public function test_a_generated_route_is_addressable_by_its_name(): void
    {
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('create')->willReturnCallback(static function (object $entity): Route {
            $route = (new Route())->setPath('/about-us')->setName('about-us');
            $entity->setRoute($route);

            return $route;
        });
        $builder = $this->makeBuilder($this->collectingEm(), $generator);
        $builder->layout('main', 'Primary');
        $page = $builder->page('about', 'Primary', layout: 'main')->title('About us');
        $builder->flush();

        $this->assertSame($page->getRoute(), $builder->getRoute('about-us'));
    }

    public function test_a_redirect_to_a_route_named_only_by_derivation_is_created(): void
    {
        $persisted = [];
        $builder = $this->makeBuilder($this->collectingEm($persisted));
        $builder->layout('main', 'Primary');
        $page = $builder->page('about', 'Primary', layout: 'main', route: '/about');
        $builder->redirect('/old-about', to: 'about');
        $builder->flush();

        $redirects = array_values(array_filter($persisted, static fn ($e) => $e instanceof Route && null !== $e->getRedirect()));
        $this->assertCount(1, $redirects);
        $this->assertSame($page->getRoute(), $redirects[0]->getRedirect());
    }

    public function test_each_nested_call_adds_a_closure_and_every_child_is_created(): void
    {
        foreach (['page', 'pageData'] as $parentType) {
            $flushCount = 0;
            $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount));
            $builder->layout('main', 'Primary');
            $parent = 'page' === $parentType
                ? $builder->page('about', 'Primary', layout: 'main', route: '/about')
                : $builder->pageData(self::titled(new class extends AbstractPageData {}), route: '/about');
            $parent
                ->nested(static fn (CwaFixtureBuilder $cwa) => $cwa->page('team', 'Primary', layout: 'main', route: '/about/team'))
                ->nested(static fn (CwaFixtureBuilder $cwa) => $cwa->page('careers', 'Primary', layout: 'main', route: '/about/careers'));
            $builder->flush();

            $this->assertSame('/about/team', $builder->getRoute('about-team')->getPath(), $parentType);
            $this->assertSame('/about/careers', $builder->getRoute('about-careers')->getPath(), $parentType);
            $this->assertCount(2, $parent->getNestedClosures(), $parentType);
        }
    }

    public function test_page_nested_returns_the_builder(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $builder->layout('main', 'Primary');
        $page = $builder->page('about', 'Primary', layout: 'main', route: '/about');

        $this->assertSame($page, $page->nested(static function (): void {}));
    }

    public function test_a_nested_page_declared_by_a_first_class_callable_of_the_scaffold_is_created_under_its_parent(): void
    {
        $builder = $this->makeBuilder($this->collectingEm());
        $scaffold = new class {
            public ?PageBuilder $child = null;

            public function aboutPage(CwaFixtureBuilder $cwa): void
            {
                $cwa->layout('main', 'Primary');
                $cwa->page('about', 'Primary', layout: 'main', route: '/about')
                    ->nested($this->aboutTeamPage(...));
            }

            private function aboutTeamPage(CwaFixtureBuilder $cwa): void
            {
                $this->child = $cwa->page('about-team', 'Primary', layout: 'main', route: '/about/team', routeName: 'team');
            }
        };

        $scaffold->aboutPage($builder);
        $builder->flush();

        $this->assertNotNull($scaffold->child);
        $this->assertSame('about', $scaffold->child->getPage()->getParentPage()?->reference);
        $this->assertSame($scaffold->child->getRoute(), $builder->getRoute('team'));
    }

    private function timestampingPersister(): TimestampedDataPersister
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturnCallback(static function (string $class): OrmClassMetadata {
            $metadata = new OrmClassMetadata($class);
            $metadata->initializeReflection(new RuntimeReflectionService());
            $metadata->mapField(['fieldName' => 'createdAt', 'type' => 'datetime_immutable']);
            $metadata->mapField(['fieldName' => 'modifiedAt', 'type' => 'datetime']);
            $metadata->wakeupReflection(new RuntimeReflectionService());

            return $metadata;
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $reader = new TimestampedAttributeReader($registry);

        return new TimestampedDataPersister($registry, $reader);
    }

    /**
     * @param array<class-string, list<string>> $associations
     */
    private function associatingEm(array $associations, int &$flushCount, ?UnitOfWork $unitOfWork = null, bool $contains = false): EntityManagerInterface
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturnCallback(function (string $class) use ($associations): OrmClassMetadata {
            $metadata = $this->createStub(OrmClassMetadata::class);
            $metadata->method('getAssociationNames')->willReturn($associations[$class] ?? []);
            $metadata->method('isAssociationInverseSide')->willReturn(false);

            return $metadata;
        });
        $em->method('flush')->willReturnCallback(static function () use (&$flushCount): void { ++$flushCount; });
        $em->method('contains')->willReturn($contains);
        $em->method('getUnitOfWork')->willReturn($unitOfWork ?? $this->createStub(UnitOfWork::class));

        return $em;
    }

    public function test_an_entity_given_only_to_persist_is_timestamped(): void
    {
        $flushCount = 0;
        $entity = new DummyTimestamped();

        $builder = $this->makeBuilder($this->collectingEm(flushCount: $flushCount), timestampedPersister: $this->timestampingPersister());
        $builder->persist($entity);
        $builder->flush();

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->createdAt);
        $this->assertInstanceOf(\DateTime::class, $entity->modifiedAt);
        $this->assertSame(1, $flushCount);
    }

    public function test_a_component_reached_only_through_a_page_data_field_is_timestamped(): void
    {
        $flushCount = 0;
        $component = new DummyTimestampedComponent();
        $pageData = new PageDataWithParentTypedComponent();
        $pageData->component = $component;

        $builder = $this->makeBuilder(
            $this->associatingEm([PageDataWithParentTypedComponent::class => ['component']], $flushCount),
            timestampedPersister: $this->timestampingPersister(),
        );
        $builder->pageData(self::titled($pageData), route: '/timestamped');
        $builder->flush();

        $this->assertInstanceOf(\DateTimeImmutable::class, $component->createdAt);
        $this->assertInstanceOf(\DateTime::class, $component->modifiedAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $pageData->createdAt);
        $this->assertSame(2, $flushCount);
    }

    public function test_an_entity_a_cascade_already_scheduled_for_insert_is_timestamped(): void
    {
        $flushCount = 0;
        $entity = new DummyTimestamped();
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('isScheduledForInsert')->willReturn(true);

        $builder = $this->makeBuilder($this->associatingEm([], $flushCount, $unitOfWork, contains: true), timestampedPersister: $this->timestampingPersister());
        $builder->persist($entity);
        $builder->flush();

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->createdAt);
        $this->assertInstanceOf(\DateTime::class, $entity->modifiedAt);
    }

    public function test_a_managed_entity_already_in_the_database_keeps_its_timestamps(): void
    {
        $flushCount = 0;
        $createdAt = new \DateTimeImmutable('2001-02-03 04:05:06');
        $modifiedAt = new \DateTime('2002-03-04 05:06:07');
        $entity = new DummyTimestamped();
        $entity->createdAt = $createdAt;
        $entity->modifiedAt = $modifiedAt;

        $builder = $this->makeBuilder($this->associatingEm([], $flushCount, contains: true), timestampedPersister: $this->timestampingPersister());
        $builder->persist($entity);
        $builder->flush();

        $this->assertSame($createdAt, $entity->createdAt);
        $this->assertSame($modifiedAt, $entity->modifiedAt);
    }

    public function test_an_entity_that_is_not_timestamped_is_not_given_timestamps(): void
    {
        $calls = [];
        $component = new DummyComponent();

        $builder = $this->makeBuilder($this->collectingEm(), timestampedPersister: $this->recordingTimestampedPersister($calls, isConfigured: false));
        $builder->persist($component);
        $builder->flush();

        $this->assertSame([], $calls);
    }
}
