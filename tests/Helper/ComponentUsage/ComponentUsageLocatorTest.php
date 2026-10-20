<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentUsage;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageLocator;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class ComponentUsageLocatorTest extends OrphanedResourceDatabaseTestCase
{
    private ComponentUsageLocator $locator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locator = new ComponentUsageLocator($this->registry, $this->publishableAttributeReader, $this->iriConverter);
    }

    public function test_nothing_to_locate_runs_no_query(): void
    {
        $this->queryLogger->queries = [];

        self::assertSame([], $this->locator->locate([], []));
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_position_on_a_routed_page_renders_on_that_page_with_its_title_and_route_path(): void
    {
        $component = $this->persist(new DummyComponent());
        $page = $this->namedPage('About', '/about');
        $position = $this->position($this->groupOf($page), $component);

        self::assertSame([[
            'componentPosition' => $this->iri($position),
            'pageDataProperty' => null,
            'pageData' => null,
            'property' => null,
            'layout' => null,
            'renderedOn' => [['iri' => $this->iri($page), 'title' => 'About', 'routePath' => '/about']],
            'renderedOnCount' => 1,
        ]], $this->locate([$position]));
    }

    public function test_a_position_in_a_layout_group_names_the_layout_with_the_lowest_reference(): void
    {
        $component = $this->persist(new DummyComponent());
        $group = $this->group('shared');
        $second = $this->namedLayout('second');
        $first = $this->namedLayout('first');
        $second->addComponentGroup($group);
        $first->addComponentGroup($group);
        $position = $this->position($group, $component);

        $located = $this->locate([$position]);

        self::assertSame(['iri' => $this->iri($first), 'reference' => 'first'], $located[0]['layout']);
        self::assertSame([], $located[0]['renderedOn']);
    }

    public function test_a_template_position_renders_on_the_template_and_its_page_data(): void
    {
        $component = $this->persist(new DummyComponent());
        $template = $this->namedPage('Template', null, true);
        $position = $this->position($this->groupOf($template), $component);
        $pageData = $this->pageData($template, 'Post', '/post');

        self::assertSame([
            ['iri' => $this->iri($pageData), 'title' => 'Post', 'routePath' => '/post'],
            ['iri' => $this->iri($template), 'title' => 'Template', 'routePath' => null],
        ], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_a_non_template_page_does_not_render_its_page_data(): void
    {
        $component = $this->persist(new DummyComponent());
        $page = $this->namedPage('Plain', '/plain');
        $position = $this->position($this->groupOf($page), $component);
        $this->pageData($page, 'Post', '/post');

        self::assertSame([['iri' => $this->iri($page), 'title' => 'Plain', 'routePath' => '/plain']], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_a_position_in_a_component_climbs_to_the_pages_and_page_data_holding_the_owner(): void
    {
        $component = $this->persist(new DummyComponent());
        $owner = $this->persist(new DummyComponent());
        $position = $this->position($this->groupOf($owner), $component);
        $page = $this->namedPage('Page', '/page');
        $this->position($this->groupOf($page), $owner);
        $template = $this->namedPage('Template', null, true);
        $pageData = $this->pageData($template, 'Held', '/held');
        $pageData->component = $owner;

        self::assertSame([
            ['iri' => $this->iri($pageData), 'title' => 'Held', 'routePath' => '/held'],
            ['iri' => $this->iri($page), 'title' => 'Page', 'routePath' => '/page'],
        ], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_a_group_owned_by_a_draft_climbs_through_its_published_component(): void
    {
        $component = $this->persist(new DummyComponent());
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $this->persist($published);
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->persist($draft);
        $position = $this->position($this->groupOf($draft), $component);
        $page = $this->namedPage('Page', '/page');
        $this->position($this->groupOf($page), $published);

        self::assertSame([['iri' => $this->iri($page), 'title' => 'Page', 'routePath' => '/page']], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_a_page_data_use_renders_on_its_page_data(): void
    {
        $template = $this->namedPage('Template', null, true);
        $pageData = $this->pageData($template, 'Held', null);
        $this->entityManager->flush();

        self::assertSame([[
            'componentPosition' => null,
            'pageDataProperty' => null,
            'pageData' => $this->iri($pageData),
            'property' => 'component',
            'layout' => null,
            'renderedOn' => [['iri' => $this->iri($pageData), 'title' => 'Held', 'routePath' => null]],
            'renderedOnCount' => 1,
        ]], $this->locator->locate([], [['pageData' => $pageData, 'property' => 'component']]));
    }

    public function test_a_dynamic_position_keeps_its_page_data_property(): void
    {
        $component = $this->persist(new DummyComponent());
        $position = $this->position($this->groupOf($this->namedPage('Template', null, true)), $component);
        $position->pageDataProperty = 'component';

        self::assertSame('component', $this->locate([$position])[0]['pageDataProperty']);
    }

    public function test_a_dynamic_position_renders_its_fallback_only_on_page_data_whose_property_is_null_or_that_has_no_such_property(): void
    {
        $component = $this->persist(new DummyComponent());
        $template = $this->namedPage('Template', null, true);
        $position = $this->position($this->groupOf($template), $component);
        $position->pageDataProperty = 'component';
        $empty = $this->pageData($template, 'Empty', '/empty');
        $overriding = $this->pageData($template, 'Overriding', '/overriding');
        $overriding->component = $this->persist(new DummyComponent());
        $withoutProperty = new PageData();
        $withoutProperty->page = $template;
        $withoutProperty->setTitle('Without property');
        $this->persist($withoutProperty);

        self::assertSame([
            ['iri' => $this->iri($empty), 'title' => 'Empty', 'routePath' => '/empty'],
            ['iri' => $this->iri($template), 'title' => 'Template', 'routePath' => null],
            ['iri' => $this->iri($withoutProperty), 'title' => 'Without property', 'routePath' => null],
        ], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_a_component_reached_through_a_page_data_property_is_not_rendered_where_that_page_data_overrides_a_dynamic_position_on_the_way(): void
    {
        $component = $this->persist(new DummyComponent());
        $container = $this->persist(new DummyComponent());
        $position = $this->position($this->groupOf($container), $component);
        $owner = $this->persist(new DummyComponent());
        $dynamic = $this->position($this->groupOf($owner), $container);
        $dynamic->pageDataProperty = 'publishableComponent';
        $template = $this->namedPage('Template', null, true);
        $kept = $this->pageData($template, 'Kept', '/kept');
        $kept->component = $owner;
        $overriding = $this->pageData($template, 'Overriding', '/overriding');
        $overriding->component = $owner;
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $overriding->publishableComponent = $this->persist($published);

        self::assertSame([['iri' => $this->iri($kept), 'title' => 'Kept', 'routePath' => '/kept']], $this->locate([$position])[0]['renderedOn']);
    }

    public function test_the_number_of_queries_is_the_same_however_many_page_data_override_a_dynamic_position(): void
    {
        $few = $this->dynamicPositionOverriddenBy(1);
        $many = $this->dynamicPositionOverriddenBy(5);
        $this->entityManager->flush();

        $this->queryLogger->queries = [];
        $fewLocated = $this->locator->locate([$few], []);
        $fewQueries = \count($this->queryLogger->queries);
        $this->queryLogger->queries = [];
        $manyLocated = $this->locator->locate([$many], []);
        $manyQueries = \count($this->queryLogger->queries);

        self::assertSame(2, $fewLocated[0]['renderedOnCount']);
        self::assertSame(2, $manyLocated[0]['renderedOnCount']);
        self::assertSame($fewQueries, $manyQueries);
    }

    public function test_overrides_cost_one_query_per_class_of_candidate_page_data_and_none_without_a_dynamic_position(): void
    {
        $static = $this->dynamicPositionOverriddenBy(1);
        $static->pageDataProperty = null;
        $dynamic = $this->dynamicPositionOverriddenBy(1);
        $this->entityManager->flush();

        $this->queryLogger->queries = [];
        $this->locator->locate([$static], []);
        $staticQueries = \count($this->queryLogger->queries);
        $this->queryLogger->queries = [];
        $this->locator->locate([$dynamic], []);
        $dynamicQueries = \count($this->queryLogger->queries);

        self::assertSame($staticQueries + 1, $dynamicQueries);
    }

    public function test_the_number_of_queries_is_the_same_however_deep_the_chain(): void
    {
        $shallow = $this->chain(1);
        $deep = $this->chain(8);
        $this->entityManager->flush();

        $this->queryLogger->queries = [];
        $shallowLocated = $this->locator->locate([$shallow], []);
        $shallowQueries = \count($this->queryLogger->queries);
        $this->queryLogger->queries = [];
        $deepLocated = $this->locator->locate([$deep], []);
        $deepQueries = \count($this->queryLogger->queries);

        self::assertSame(1, $shallowLocated[0]['renderedOnCount']);
        self::assertSame(1, $deepLocated[0]['renderedOnCount']);
        self::assertSame($shallowQueries, $deepQueries);
    }

    /**
     * @param list<ComponentPosition> $positions
     *
     * @return list<array<string, mixed>>
     */
    private function locate(array $positions): array
    {
        $this->entityManager->flush();

        return $this->locator->locate($positions, []);
    }

    private function dynamicPositionOverriddenBy(int $overriding): ComponentPosition
    {
        $template = $this->namedPage('Template ' . $overriding, null, true);
        $position = $this->position($this->groupOf($template), $this->persist(new DummyComponent()));
        $position->pageDataProperty = 'component';
        $this->pageData($template, 'Empty', null);
        for ($index = 0; $index < $overriding; ++$index) {
            $this->pageData($template, 'Overriding ' . $index, null)->component = $this->persist(new DummyComponent());
        }

        return $position;
    }

    private function chain(int $depth): ComponentPosition
    {
        $component = $this->persist(new DummyComponent());
        $position = null;
        $current = $component;
        for ($level = 0; $level < $depth; ++$level) {
            $owner = $this->persist(new DummyComponent());
            $placed = $this->position($this->groupOf($owner), $current);
            $position ??= $placed;
            $current = $owner;
        }
        $this->position($this->groupOf($this->namedPage('Top', '/top-' . $depth)), $current);
        \assert($position instanceof ComponentPosition);

        return $position;
    }

    private function groupOf(Page|Layout|AbstractComponent $owner): ComponentGroup
    {
        $group = $this->group('group-' . bin2hex(random_bytes(4)));
        $owner->addComponentGroup($group);

        return $group;
    }

    private function namedPage(string $title, ?string $path, bool $isTemplate = false): Page
    {
        $page = $this->page();
        $page->isTemplate = $isTemplate;
        $page->setTitle($title);
        if (null !== $path) {
            $this->persist((new Route())->setPath($path)->setName($path)->setPage($page));
        }

        return $page;
    }

    private function namedLayout(string $reference): Layout
    {
        $layout = $this->layout();
        $layout->reference = $reference;

        return $layout;
    }

    private function pageData(Page $template, string $title, ?string $path): PageDataWithComponent
    {
        $pageData = new PageDataWithComponent();
        $pageData->page = $template;
        $pageData->setTitle($title);
        $this->persist($pageData);
        if (null !== $path) {
            $this->persist((new Route())->setPath($path)->setName($path)->setPageData($pageData));
        }

        return $pageData;
    }
}
