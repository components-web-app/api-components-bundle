<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Component;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class ComponentUsageLocationsTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use SiteTrait;

    /** @var list<object> */
    private static array $site = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$site = [];
    }

    public function test_a_position_on_a_routed_page_is_one_location_rendered_on_that_page(): void
    {
        $component = new DummyComponent();
        $page = self::routedPage('About us', '/about');
        $position = self::place($component, self::groupOf($page));
        self::saveSite();

        self::requestUsage($component);

        self::assertSame([
            [
                'componentPosition' => self::iriOf($position),
                'pageDataProperty' => null,
                'pageData' => null,
                'property' => null,
                'layout' => null,
                'renderedOn' => [self::rendered($page, 'About us', '/about')],
                'renderedOnCount' => 1,
            ],
        ], self::lastJson()['locations']);
        self::assertJsonNodeSame('total', 1);
    }

    public function test_a_layout_position_names_the_layout_and_renders_on_no_listed_page(): void
    {
        $component = new DummyComponent();
        $layout = self::newLayout('primary');
        self::$site[] = $layout;
        $position = self::place($component, self::groupOf($layout));
        self::saveSite();

        self::requestUsage($component);

        self::assertSame([
            [
                'componentPosition' => self::iriOf($position),
                'pageDataProperty' => null,
                'pageData' => null,
                'property' => null,
                'layout' => ['iri' => self::iriOf($layout), 'reference' => 'primary'],
                'renderedOn' => [],
                'renderedOnCount' => 0,
            ],
        ], self::lastJson()['locations']);
    }

    public function test_a_page_template_position_renders_on_the_template_and_every_page_data_using_it_routed_by_path_then_unrouted_by_title(): void
    {
        $component = new DummyComponent();
        $template = self::templatePage('Post template');
        self::place($component, self::groupOf($template));
        $second = self::pageData($template, 'Second post', '/blog/b');
        $first = self::pageData($template, 'First post', '/blog/a');
        $unrouted = self::pageData($template, 'An unrouted post', null);
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeSame('locations[0].renderedOn', [
            self::rendered($first, 'First post', '/blog/a'),
            self::rendered($second, 'Second post', '/blog/b'),
            self::rendered($unrouted, 'An unrouted post', null),
            self::rendered($template, 'Post template', null),
        ]);
        self::assertJsonNodeSame('locations[0].renderedOnCount', 4);
    }

    public function test_a_position_inside_a_nested_component_chain_renders_on_every_page_the_chain_reaches(): void
    {
        $component = new DummyComponent();
        $inner = new DummyComponent();
        $outer = new DummyComponent();
        $position = self::place($component, self::groupOf($inner));
        self::place($inner, self::groupOf($outer));
        $one = self::routedPage('One', '/one');
        $two = self::routedPage('Two', '/two');
        self::place($outer, self::groupOf($one));
        self::place($outer, self::groupOf($two));
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeCount('locations', 1);
        self::assertJsonNodeSame('locations[0].componentPosition', self::iriOf($position));
        self::assertJsonNodeSame('locations[0].renderedOn', [
            self::rendered($one, 'One', '/one'),
            self::rendered($two, 'Two', '/two'),
        ]);
    }

    public function test_a_nested_component_held_by_a_page_data_property_renders_on_that_page_data(): void
    {
        $component = new DummyComponent();
        $owner = new DummyComponent();
        self::place($component, self::groupOf($owner));
        $template = self::templatePage('Event template');
        $pageData = self::pageData($template, 'Launch', '/launch');
        $pageData->component = $owner;
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeSame('locations[0].renderedOn', [self::rendered($pageData, 'Launch', '/launch')]);
    }

    public function test_a_page_data_property_holding_the_component_is_a_location_rendered_on_that_page_data(): void
    {
        $component = new DummyComponent();
        $template = self::templatePage('Event template');
        $pageData = self::pageData($template, 'Launch', '/launch');
        $pageData->component = $component;
        self::$site[] = $component;
        self::saveSite();

        self::requestUsage($component);

        self::assertSame([
            [
                'componentPosition' => null,
                'pageDataProperty' => null,
                'pageData' => self::iriOf($pageData),
                'property' => 'component',
                'layout' => null,
                'renderedOn' => [self::rendered($pageData, 'Launch', '/launch')],
                'renderedOnCount' => 1,
            ],
        ], self::lastJson()['locations']);
        self::assertJsonNodeSame('pageDataCount', 1);
    }

    #[DataProvider('publishedOrDraft')]
    public function test_a_published_component_and_its_draft_list_the_same_locations(string $requested): void
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        self::$site[] = $draft;
        $page = self::routedPage('Home', '/');
        $position = self::place($published, self::groupOf($page));
        $template = self::templatePage('Event template');
        $pageData = self::pageData($template, 'Launch', '/launch');
        $pageData->publishableComponent = $published;
        self::saveSite();

        self::requestUsage('published' === $requested ? $published : $draft);

        self::assertJsonNodeSame('total', 2);
        self::assertJsonNodeCount('locations', 2);
        self::assertJsonNodeSame('locations[0].componentPosition', self::iriOf($position));
        self::assertJsonNodeSame('locations[0].renderedOn', [self::rendered($page, 'Home', '/')]);
        self::assertJsonNodeSame('locations[1].pageData', self::iriOf($pageData));
        self::assertJsonNodeSame('locations[1].property', 'publishableComponent');
    }

    public static function publishedOrDraft(): iterable
    {
        yield 'the published component' => ['published'];
        yield 'the draft' => ['draft'];
    }

    public function test_a_dynamic_position_whose_fallback_is_the_component_is_listed_and_flagged_with_its_page_data_property(): void
    {
        $component = new DummyComponent();
        $template = self::templatePage('Event template');
        $position = self::place($component, self::groupOf($template));
        $position->pageDataClass = PageDataWithComponent::class;
        $position->pageDataProperty = 'component';
        $pageData = self::pageData($template, 'Launch', '/launch');
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeSame('positionCount', 1);
        self::assertJsonNodeSame('locations[0].componentPosition', self::iriOf($position));
        self::assertJsonNodeSame('locations[0].pageDataProperty', 'component');
        self::assertJsonNodeSame('locations[0].renderedOn', [
            self::rendered($pageData, 'Launch', '/launch'),
            self::rendered($template, 'Event template', null),
        ]);
    }

    public function test_a_cycle_already_in_the_database_terminates_and_still_lists_the_pages_it_reaches(): void
    {
        $component = new DummyComponent();
        $first = new DummyComponent();
        $second = new DummyComponent();
        $firstGroup = self::groupOf($first);
        self::place($component, $firstGroup);
        self::place($second, self::groupOf($first, 'second'));
        self::place($first, self::groupOf($second));
        self::place($second, $firstGroup);
        $page = self::routedPage('Cycle', '/cycle');
        self::place($second, self::groupOf($page));
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeSame('locations[0].renderedOn', [self::rendered($page, 'Cycle', '/cycle')]);
    }

    public function test_locations_are_ordered_layouts_first_then_routed_uses_by_path_then_unrouted_uses_by_title(): void
    {
        $component = new DummyComponent();
        $zeta = self::place($component, self::groupOf(self::routedPage('Zeta', '/zeta')));
        $layout = self::newLayout('main');
        self::$site[] = $layout;
        $inLayout = self::place($component, self::groupOf($layout));
        $beta = self::place($component, self::groupOf(self::unroutedPage('Beta')));
        $alpha = self::place($component, self::groupOf(self::routedPage('Alpha', '/alpha')));
        $unplaced = self::place($component, self::newComponentGroup('unplaced'));
        $unroutedAlpha = self::place($component, self::groupOf(self::unroutedPage('Alpha unrouted')));
        $template = self::templatePage('Event template');
        $pageData = self::pageData($template, 'Middle', '/middle');
        $pageData->component = $component;
        self::saveSite();

        self::requestUsage($component);

        $order = array_map(static fn (array $location): ?string => $location['componentPosition'] ?? $location['pageData'], self::lastJson()['locations']);
        self::assertSame([
            self::iriOf($inLayout),
            self::iriOf($alpha),
            self::iriOf($pageData),
            self::iriOf($zeta),
            self::iriOf($unroutedAlpha),
            self::iriOf($beta),
            self::iriOf($unplaced),
        ], $order);
    }

    public function test_locations_are_capped_at_50_with_the_exact_total(): void
    {
        $component = new DummyComponent();
        $group = self::groupOf(self::routedPage('Long page', '/long'));
        for ($sortValue = 0; $sortValue < 52; ++$sortValue) {
            self::place($component, $group, $sortValue);
        }
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeSame('positionCount', 52);
        self::assertJsonNodeSame('total', 52);
        self::assertJsonNodeCount('locations', 50);
    }

    public function test_rendered_on_is_capped_at_20_with_the_exact_count(): void
    {
        $component = new DummyComponent();
        $template = self::templatePage('Post template');
        self::place($component, self::groupOf($template));
        for ($index = 10; $index < 31; ++$index) {
            self::pageData($template, 'Post ' . $index, '/posts/' . $index);
        }
        self::saveSite();

        self::requestUsage($component);

        self::assertJsonNodeCount('locations[0].renderedOn', 20);
        self::assertJsonNodeSame('locations[0].renderedOnCount', 22);
        self::assertJsonNodeSame('locations[0].renderedOn[0].routePath', '/posts/10');
        self::assertJsonNodeSame('locations[0].renderedOn[19].routePath', '/posts/29');
    }

    public function test_an_anonymous_caller_reading_the_usage_of_a_public_component_gets_the_counts_without_locations(): void
    {
        $component = new DummyComponent();
        self::place($component, self::groupOf(self::routedPage('Live', '/live')));
        self::saveSite();

        self::requestJsonLd(static::createClient(), 'GET', self::iriOf($component) . '/usage');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('total', 1);
        self::assertArrayNotHasKey('locations', self::lastJson());
    }

    public function test_a_user_who_is_not_an_admin_gets_the_counts_without_locations(): void
    {
        $component = new DummyComponent();
        self::place($component, self::groupOf(self::routedPage('Live', '/live')));
        self::saveSite();
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'GET', self::iriOf($component) . '/usage');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('total', 1);
        self::assertArrayNotHasKey('locations', self::lastJson());
    }

    #[DataProvider('callers')]
    public function test_the_usage_is_never_stored_by_a_shared_cache(string $caller): void
    {
        $component = new DummyComponent();
        self::place($component, self::groupOf(self::routedPage('Live', '/live')));
        self::saveSite();
        $client = static::createClient();
        if ('admin' === $caller) {
            self::loginAsAdmin($client);
        }

        self::requestJsonLd($client, 'GET', self::iriOf($component) . '/usage');

        self::assertResponseStatusCodeSame(200);
        $cacheControl = (string) $client->getResponse()?->getHeaders(false)['cache-control'][0];
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }

    public static function callers(): iterable
    {
        yield 'an anonymous caller' => ['anonymous'];
        yield 'an admin' => ['admin'];
    }

    public function test_the_number_of_queries_does_not_grow_with_the_depth_of_the_component_chain(): void
    {
        $shallow = self::componentAtDepth(1, 'shallow');
        $deep = self::componentAtDepth(6, 'deep');
        self::saveSite();

        $shallowQueries = self::countQueriesCreatingUsage($shallow);
        $deepQueries = self::countQueriesCreatingUsage($deep);

        self::assertSame($shallowQueries, $deepQueries);
        self::assertLessThanOrEqual(12, $deepQueries);
    }

    private static function componentAtDepth(int $depth, string $name): DummyComponent
    {
        $component = new DummyComponent();
        $current = $component;
        for ($level = 0; $level < $depth; ++$level) {
            $owner = new DummyComponent();
            self::place($current, self::groupOf($owner, $name . $level));
            $current = $owner;
        }
        $template = self::templatePage($name . ' template');
        self::place($current, self::groupOf($template, $name . '-page'));
        self::pageData($template, $name . ' post', '/' . $name);
        $layout = self::newLayout($name . '-layout');
        self::$site[] = $layout;
        self::place($current, self::groupOf($layout, $name . '-layout'));

        return $component;
    }

    private static function countQueriesCreatingUsage(DummyComponent $component): int
    {
        $manager = static::getEntityManager();
        $manager->clear();
        $loaded = $manager->find(DummyComponent::class, $component->getId());
        self::assertInstanceOf(ComponentInterface::class, $loaded);
        $factory = static::getContainer()->get('silverback.metadata_factory.component_usage');
        self::assertInstanceOf(ComponentUsageMetadataFactory::class, $factory);

        QueryCountingMiddleware::reset();
        $usage = $factory->createWithLocations($loaded);
        $count = QueryCountingMiddleware::count();
        self::assertCount(1, $usage->getLocations() ?? []);
        self::assertSame(2, ($usage->getLocations() ?? [])[0]['renderedOnCount']);

        return $count;
    }

    private static function requestUsage(AbstractComponent $component): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::requestJsonLd($client, 'GET', self::iriOf($component) . '/usage');
        self::assertResponseStatusCodeSame(200);
    }

    private static function saveSite(): void
    {
        static::persistAndFlush(...self::$site);
    }

    /**
     * @return array{iri: string, title: ?string, routePath: ?string}
     */
    private static function rendered(object $pageOrPageData, ?string $title, ?string $routePath): array
    {
        return ['iri' => self::iriOf($pageOrPageData), 'title' => $title, 'routePath' => $routePath];
    }

    private static function routedPage(string $title, string $path): Page
    {
        $page = self::unroutedPage($title);
        $route = self::newRoute($path, 'route' . \count(self::$site))->setPage($page);
        self::$site[] = $route;

        return $page;
    }

    private static function unroutedPage(string $title): Page
    {
        $page = self::newPage('page' . \count(self::$site));
        $page->setTitle($title);
        self::$site[] = $page;

        return $page;
    }

    private static function templatePage(string $title): Page
    {
        $page = self::newPage('template' . \count(self::$site), true);
        $page->setTitle($title);
        self::$site[] = $page;

        return $page;
    }

    private static function pageData(Page $template, string $title, ?string $path): PageDataWithComponent
    {
        $pageData = new PageDataWithComponent();
        $pageData->page = $template;
        $pageData->setTitle($title);
        self::$site[] = $pageData;
        if (null !== $path) {
            self::$site[] = self::newRoute($path, 'route' . \count(self::$site))->setPageData($pageData);
        }

        return $pageData;
    }

    private static function groupOf(Page|Layout|AbstractComponent $owner, string $name = 'group'): ComponentGroup
    {
        $group = self::newComponentGroup($name . \count(self::$site));
        $owner->addComponentGroup($group);
        if (!\in_array($owner, self::$site, true)) {
            self::$site[] = $owner;
        }
        self::$site[] = $group;

        return $group;
    }

    private static function place(AbstractComponent $component, ComponentGroup $group, int $sortValue = 0): ComponentPosition
    {
        if (!\in_array($component, self::$site, true)) {
            self::$site[] = $component;
        }
        if (!\in_array($group, self::$site, true)) {
            self::$site[] = $group;
        }
        $position = self::newComponentPosition($group, $component, $sortValue);
        self::$site[] = $position;

        return $position;
    }
}
