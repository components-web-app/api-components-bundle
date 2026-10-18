<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\Assignment;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlacer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlanner;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\IriValueLinker;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\PropertyValueWriter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Tests\Command\Fixture\PageDataWithFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRoute;

class ComponentPlacerTest extends TestCase
{
    use PlannerTestTrait;

    private const string NOW = '2050-01-01T00:00:00+00:00';

    private NotReproducedReport $report;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function test_positions_are_placed_in_sort_order_with_one_plan_per_component_counting_each_use(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $shared = new DummyComponent();
        $first = new DummyComponent();
        $this->position($group, $shared, 20);
        $this->position($group, $first, 10);
        $this->position($group, $shared, 30);
        $placer = $this->placer();
        $method = $this->methodFor($layout);

        $placer->placeIn($method);

        $items = $method->groups[0]->items;
        self::assertSame('top', $method->groups[0]->name);
        self::assertCount(3, $items);
        self::assertSame($items[1], $items[2]);
        self::assertSame([$items[0], $items[1]], $placer->plans());
        self::assertInstanceOf(ComponentPlan::class, $items[0]);
        self::assertInstanceOf(ComponentPlan::class, $items[1]);
        self::assertSame($first, $items[0]->component);
        self::assertSame('DummyComponent', $items[0]->class);
        self::assertSame(1, $items[0]->uses);
        self::assertSame(2, $items[1]->uses);
        self::assertSame($method, $items[0]->scope);
        self::assertSame([spl_object_id($method) => $method], $items[1]->scopes);
        self::assertSame(['main', 'top'], $items[0]->phrases);
    }

    public function test_a_component_is_named_after_its_identifying_value(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $link = new DummyNavigationLink();
        $link->label = 'Contact us';
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $link, 10);

        self::assertSame(['Contact us'], $this->placeAll($layout)[0]->phrases);
    }

    public function test_a_component_used_in_two_methods_keeps_the_first_scope_and_records_both(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout);
        $link = new DummyComponent();
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $link, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $link, 10);
        $placer = $this->placer();
        $layoutMethod = $this->methodFor($layout);
        $pageMethod = $this->methodFor($page);

        $placer->placeIn($layoutMethod);
        $placer->placeIn($pageMethod);

        $plan = $placer->plans()[0];
        self::assertSame($layoutMethod, $plan->scope);
        self::assertSame([spl_object_id($layoutMethod), spl_object_id($pageMethod)], array_keys($plan->scopes));
        self::assertSame(2, $plan->uses);
    }

    public function test_a_group_shared_by_two_owners_is_placed_once(): void
    {
        $first = $this->page('first', null);
        $second = $this->page('second', null);
        $group = $this->group('shared_site', '/elsewhere', $first);
        $second->getComponentGroups()->add($group);
        $this->position($group, new DummyComponent(), 10);
        $placer = $this->placer();
        $firstMethod = $this->methodFor($first);
        $secondMethod = $this->methodFor($second);

        $placer->placeIn($firstMethod);
        $placer->placeIn($secondMethod);

        self::assertCount(1, $firstMethod->groups[0]->items);
        self::assertSame(["'shared'", "locationReference: 'site'"], $secondMethod->groups[0]->arguments);
        self::assertSame([], $secondMethod->groups[0]->items);
    }

    public function test_a_group_that_cannot_be_planned_is_left_out(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $this->position($this->group('orphan', '/_/layouts/main', $layout), new DummyComponent(), 10);
        $placer = $this->placer();
        $method = $this->methodFor($layout);

        $placer->placeIn($method);

        self::assertSame([], $method->groups);
        self::assertSame([], $placer->plans());
    }

    public function test_a_page_data_position_is_written_and_its_fallback_component_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $typed = $this->position($group, new DummyComponent(), 10);
        $typed->pageDataClass = PageDataWithComponent::class;
        $typed->pageDataProperty = 'component';
        $untyped = $this->position($group, new DummyComponent(), 20);
        $untyped->component = null;
        $untyped->pageDataProperty = 'hero';
        $placer = $this->placer();
        $method = $this->methodFor($layout);

        $placer->placeIn($method);

        self::assertSame(["pageDataPosition(PageDataWithComponent::class, 'component')", "pageDataPosition(null, 'hero')"], $method->groups[0]->items);
        self::assertSame([], $placer->plans());
        self::assertSame(['The fallback component of the page data position "component" in the component group "top_/_/layouts/main".'], $this->report->all());
    }

    public function test_page_data_fields_are_assigned_its_component_placed_and_its_route_linked(): void
    {
        $about = $this->route('/about', 'about');
        $withComponent = new PageDataWithComponent();
        $withComponent->setTitle('Event');
        $withComponent->component = new DummyComponent();
        $withComponent->publishableComponent = new DummyPublishableComponent();
        $withRoute = new PageDataWithRoute();
        $withRoute->relatedRoute = $about;
        $placer = $this->placer(new SiteSnapshot(routes: [$about]));
        $componentMethod = new MethodPlan(MethodKind::PageData, 'eventPageData', $withComponent);
        $routeMethod = new MethodPlan(MethodKind::PageData, 'pageData', $withRoute);

        $placer->placeIn($componentMethod);
        $placer->placeIn($routeMethod);

        [$plan, $publishablePlan] = $placer->plans();
        self::assertEquals([new Assignment('title', "'Event'", 'setTitle'), new Assignment('component', $plan), new Assignment('publishableComponent', $publishablePlan)], $componentMethod->entityAssignments);
        self::assertSame(['Event', 'component'], $plan->phrases);
        self::assertSame(1, $plan->uses);
        self::assertSame(["linkRoute('relatedRoute', routeName: 'about')"], $routeMethod->calls);
        self::assertSame([], $routeMethod->entityAssignments);
    }

    public function test_an_iri_of_a_site_page_in_a_component_field_is_linked_and_left_out_of_the_value_written(): void
    {
        $blog = $this->page('blog', null);
        $collection = new Collection();
        $collection->setResourceIri('/_/pages/blog');
        $collection->setDefaultQueryParameters(['pagination' => false, 'parentPage' => '/_/pages/blog']);

        $plan = $this->placeAll($this->layoutWith($collection), site: new SiteSnapshot(pages: [$blog]))[0];

        self::assertSame(["linkIri('resourceIri', page: 'blog')", "linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog')"], $plan->links);
        self::assertEquals([new Assignment('defaultQueryParameters', "['pagination' => false, 'parentPage' => null]", 'setDefaultQueryParameters')], $plan->assignments);
        self::assertSame([], $this->report->all());
    }

    public function test_an_iri_of_a_site_page_in_a_page_data_field_is_linked_on_the_page_data(): void
    {
        $blog = $this->page('blog', null);
        $listing = new PageDataWithFilters();
        $listing->filters = ['parentPage' => '/_/pages/blog'];
        $placer = $this->placer(new SiteSnapshot(pages: [$blog]));
        $method = new MethodPlan(MethodKind::PageData, 'listingPageData', $listing);

        $placer->placeIn($method);

        self::assertSame(["linkIri('filters', key: 'parentPage', page: 'blog')"], $method->calls);
        self::assertEquals([new Assignment('filters', "['parentPage' => null]")], $method->entityAssignments);
    }

    public function test_untitled_page_data_names_its_component_after_the_property_and_a_route_outside_the_site_is_reported(): void
    {
        $withComponent = new PageDataWithComponent();
        $withComponent->component = new DummyComponent();
        $withRoute = new PageDataWithRoute();
        $withRoute->relatedRoute = $this->route('/elsewhere', 'elsewhere');
        $placer = $this->placer();
        $routeMethod = new MethodPlan(MethodKind::PageData, 'pageData', $withRoute);

        $placer->placeIn(new MethodPlan(MethodKind::PageData, 'pageData', $withComponent));
        $placer->placeIn($routeMethod);

        self::assertSame(['component'], $placer->plans()[0]->phrases);
        self::assertSame([], $routeMethod->calls);
        self::assertSame([], $routeMethod->entityAssignments);
        self::assertStringContainsString(PageDataWithRoute::class . '::$relatedRoute refers to', $this->report->all()[0]);
    }

    public function test_a_method_without_a_layout_page_or_page_data_places_nothing(): void
    {
        $placer = $this->placer();
        $placer->placeIn(new MethodPlan(MethodKind::Redirects, 'redirects'));

        self::assertSame([], $placer->plans());
    }

    public function test_a_component_that_only_needs_publishing_now_is_published_now_and_a_scheduled_one_at_its_date(): void
    {
        $past = new DummyPublishableComponent();
        $past->setPublishedAt(new \DateTime('2049-12-31T23:59:59+00:00'));
        $now = new DummyPublishableComponent();
        $now->setPublishedAt(new \DateTime(self::NOW));
        $scheduled = new DummyPublishableComponent();
        $scheduled->setPublishedAt(new \DateTime('2050-01-01T00:00:01+00:00'));
        $unpublished = new DummyPublishableComponent();

        [$pastPlan, $nowPlan, $scheduledPlan, $unpublishedPlan] = $this->placeAll($this->layoutWith($past, $now, $scheduled, $unpublished));

        self::assertTrue($pastPlan->publishNow);
        self::assertNull($pastPlan->publishAt);
        self::assertTrue($nowPlan->publishNow);
        self::assertFalse($scheduledPlan->publishNow);
        self::assertSame("new \\DateTimeImmutable('2050-01-01T00:00:01+00:00')", $scheduledPlan->publishAt);
        self::assertFalse($unpublishedPlan->publishNow);
        self::assertNull($unpublishedPlan->publishAt);
    }

    public function test_keep_timestamps_writes_a_past_publish_date_exactly(): void
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));

        $plan = $this->placeAll($this->layoutWith($published), keepTimestamps: true)[0];

        self::assertFalse($plan->publishNow);
        self::assertSame("new \\DateTimeImmutable('2020-01-01T00:00:00+00:00')", $plan->publishAt);
    }

    public function test_a_draft_placed_nowhere_is_planned_with_its_published_component(): void
    {
        $published = new DummyPublishableComponent();
        $published->reference = 'Welcome';
        $draft = $this->draftOf($published);
        $draft->reference = 'Welcome back';

        $plans = $this->placeAll($this->layoutWith($published));

        self::assertCount(2, $plans);
        [$publishedPlan, $draftPlan] = $plans;
        self::assertSame($draftPlan, $publishedPlan->draft);
        self::assertSame($publishedPlan, $draftPlan->published);
        self::assertSame($draft, $draftPlan->component);
        self::assertSame($publishedPlan->scope, $draftPlan->scope);
        self::assertSame(['Welcome back'], $draftPlan->phrases);
        self::assertSame(0, $draftPlan->uses);
        self::assertNull($draftPlan->draft);
    }

    public function test_a_group_owned_by_a_draft_is_reported_and_left_out(): void
    {
        $published = new DummyPublishableComponent();
        $draft = $this->draftOf($published);
        $this->ownGroup($draft, 'panels');
        $this->ownGroup($draft, 'tabs');

        $plans = $this->placeAll($this->layoutWith($published));

        self::assertSame([], $plans[1]->groups);
        self::assertSame(['main', 'top'], $plans[1]->phrases);
        self::assertSame([
            'Component group "panels_/component/dummy_components/' . spl_object_id($draft) . '" is owned by a draft, which the fixture builder cannot give a group.',
            'Component group "tabs_/component/dummy_components/' . spl_object_id($draft) . '" is owned by a draft, which the fixture builder cannot give a group.',
        ], $this->report->all());
    }

    public function test_a_draft_in_a_position_of_its_own_is_placed_as_its_published_component_and_reported(): void
    {
        $published = new DummyPublishableComponent();
        $draft = $this->draftOf($published);
        $placer = $this->placer();
        $method = $this->methodFor($this->layoutWith($published, $draft));

        $placer->placeIn($method);
        $placer->describeAll();

        [$publishedPlan, $draftPlan] = $placer->plans();
        self::assertSame($published, $publishedPlan->component);
        self::assertSame(2, $publishedPlan->uses);
        self::assertSame([$publishedPlan, $publishedPlan], $method->groups[0]->items);
        self::assertSame($draftPlan, $publishedPlan->draft);
        self::assertSame(0, $draftPlan->uses);
        self::assertSame([], $placer->draftPairs());
        self::assertSame(['A position holds a draft of a published component, which is generated as the published component a write would store (1 entity).'], $this->report->all());
    }

    public function test_a_draft_placed_without_its_published_component_places_the_published_component(): void
    {
        $published = new DummyPublishableComponent();
        $published->reference = 'Welcome';
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $draft = $this->draftOf($published);
        $draft->reference = 'Welcome back';
        $placer = $this->placer();
        $method = $this->methodFor($this->layoutWith($draft));

        $placer->placeIn($method);
        $placer->describeAll();

        [$publishedPlan, $draftPlan] = $placer->plans();
        self::assertSame($published, $publishedPlan->component);
        self::assertSame([$publishedPlan], $method->groups[0]->items);
        self::assertSame(1, $publishedPlan->uses);
        self::assertSame(['Welcome'], $publishedPlan->phrases);
        self::assertTrue($publishedPlan->publishNow);
        self::assertSame($draftPlan, $publishedPlan->draft);
        self::assertSame(0, $draftPlan->uses);
        self::assertSame([], $placer->draftPairs());
        self::assertSame(['A position holds a draft of a published component, which is generated as the published component a write would store (1 entity).'], $this->report->all());
    }

    public function test_a_page_data_property_holding_a_draft_is_assigned_its_published_component_and_reported(): void
    {
        $published = new DummyPublishableComponent();
        $draft = $this->draftOf($published);
        $first = new PageDataWithComponent();
        $first->publishableComponent = $draft;
        $second = new PageDataWithComponent();
        $second->publishableComponent = $draft;
        $placer = $this->placer();
        $method = new MethodPlan(MethodKind::PageData, 'pageData', $first);

        $placer->placeIn($method);
        $placer->placeIn(new MethodPlan(MethodKind::PageData, 'otherPageData', $second));

        $plan = $placer->plans()[0];
        self::assertSame($published, $plan->component);
        self::assertSame(2, $plan->uses);
        self::assertEquals([new Assignment('publishableComponent', $plan)], $method->entityAssignments);
        self::assertSame(['The page data property ' . PageDataWithComponent::class . '::$publishableComponent holds a draft of a published component, which is generated as the published component a write would store (2 page data).'], $this->report->all());
    }

    public function test_a_draft_related_to_a_placed_component_is_paired_with_its_published_component(): void
    {
        $published = new DummyPublishableComponent();
        $draft = $this->draftOf($published);
        $card = new DummyComponentWithRelated();
        $card->related = $draft;
        $placer = $this->placer();
        $method = $this->methodFor($this->layoutWith($card, $published));

        $placer->placeIn($method);
        $placer->describeAll();

        [$cardPlan, $publishedPlan, $draftPlan] = $placer->plans();
        self::assertSame($card, $cardPlan->component);
        self::assertSame($draft, $draftPlan->component);
        self::assertNull($publishedPlan->draft);
        self::assertSame($publishedPlan, $draftPlan->published);
        self::assertSame([[$publishedPlan, $draftPlan]], $placer->draftPairs());
        self::assertSame([], $this->report->all());
    }

    public function test_a_group_owned_by_a_component_is_placed_in_the_owner_method_named_after_the_owner(): void
    {
        $tabs = new DummyNavigationLink();
        $tabs->label = 'Tabs';
        $panel = new DummyComponent();
        $this->position($this->ownGroup($tabs, 'panels'), $panel, 10);

        [$tabsPlan, $panelPlan] = $this->placeAll($this->layoutWith($tabs));

        self::assertSame('panels', $tabsPlan->groups[0]->name);
        self::assertSame([$panelPlan], $tabsPlan->groups[0]->items);
        self::assertSame(['Tabs', 'panels'], $panelPlan->phrases);
        self::assertSame($tabsPlan->scope, $panelPlan->scope);
    }

    public function test_a_component_without_an_identifying_value_passes_its_phrases_to_what_its_groups_and_relations_hold(): void
    {
        $tabs = new DummyComponentWithRelated();
        $tabs->related = new DummyComponent();
        $this->position($this->ownGroup($tabs, 'panels'), new DummyComponent(), 10);

        [$tabsPlan, $panelPlan, $relatedPlan] = $this->placeAll($this->layoutWith($tabs));

        self::assertSame(['main', 'top'], $tabsPlan->phrases);
        self::assertSame(['main', 'top', 'panels'], $panelPlan->phrases);
        self::assertSame(['main', 'top', 'related'], $relatedPlan->phrases);
    }

    public function test_related_components_are_recorded_and_one_placed_nowhere_is_planned_in_the_same_scope(): void
    {
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $teaser = new DummyComponentWithRelated();
        $teaser->name = 'Teaser';
        $card->related = $teaser;
        $loose = new DummyComponent();
        $teaser->related = $loose;
        $placer = $this->placer();
        $method = $this->methodFor($this->layoutWith($card, $teaser));

        $placer->placeIn($method);
        $placer->describeAll();

        [$cardPlan, $teaserPlan, $loosePlan] = $placer->plans();
        $relations = $placer->relations();
        self::assertCount(2, $relations);
        self::assertSame([$cardPlan, 'related', $teaserPlan], [$relations[0][0], $relations[0][1]->property, $relations[0][2]]);
        self::assertSame([$teaserPlan, 'related', $loosePlan], [$relations[1][0], $relations[1][1]->property, $relations[1][2]]);
        self::assertSame(PropertyValue::COMPONENT, $relations[0][1]->kind);
        self::assertTrue($cardPlan->related);
        self::assertTrue($teaserPlan->related);
        self::assertTrue($loosePlan->related);
        self::assertSame($method, $loosePlan->scope);
        self::assertSame(0, $loosePlan->uses);
        self::assertSame(['Teaser', 'related'], $loosePlan->phrases);
        self::assertEquals([new Assignment('name', "'Card'")], $cardPlan->assignments);
    }

    public function test_a_route_link_names_its_route_and_a_route_outside_the_site_is_reported(): void
    {
        $about = $this->route('/about', 'about');
        $linked = new DummyNavigationLink();
        $linked->route = $about;
        $outside = new DummyNavigationLink();
        $outside->route = $this->route('/elsewhere', 'elsewhere');

        [$linkedPlan, $outsidePlan] = $this->placeAll($this->layoutWith($linked, $outside), site: new SiteSnapshot(redirects: [$about]));

        self::assertSame(["linkRoute('route', routeName: 'about')"], $linkedPlan->links);
        self::assertSame([], $outsidePlan->links);
        self::assertSame([], $outsidePlan->assignments);
        self::assertStringContainsString(DummyNavigationLink::class . '::$route refers to', $this->report->all()[0]);
    }

    public function test_a_field_is_assigned_and_one_that_cannot_be_written_is_left_out(): void
    {
        $writable = new DummyComponent();
        $writable->uiClassNames = ['a'];
        $unwritable = new DummyComponent();
        $unwritable->uiClassNames = [new \stdClass()];

        [$writablePlan, $unwritablePlan] = $this->placeAll($this->layoutWith($writable, $unwritable));

        self::assertEquals([new Assignment('uiClassNames', "['a']")], $writablePlan->assignments);
        self::assertSame([], $unwritablePlan->assignments);
        self::assertSame(['The value of ' . DummyComponent::class . '::$uiClassNames cannot be written as PHP (1 component).'], $this->report->all());
    }

    private function placer(SiteSnapshot $site = new SiteSnapshot(), bool $keepTimestamps = false): ComponentPlacer
    {
        $registry = $this->registry();
        $this->report = new NotReproducedReport($this->iriConverter());
        $imports = new ImportCollector('SiteScaffold');
        $exporter = new PhpValueExporter();
        $stateReader = $this->stateReader($registry);
        $values = new PropertyValueWriter($stateReader, $this->storedFileExporter(), $imports, $exporter, $this->report);

        return new ComponentPlacer($site, $stateReader, $values, new IriValueLinker($site, $this->iriConverter(), $exporter, $this->report), new GroupPlanner($registry, $this->iriConverter(), $imports, $exporter, $this->report), $imports, $exporter, $this->report, new \DateTimeImmutable(self::NOW), $keepTimestamps);
    }

    /**
     * @return list<ComponentPlan>
     */
    private function placeAll(Layout $layout, bool $keepTimestamps = false, SiteSnapshot $site = new SiteSnapshot()): array
    {
        $placer = $this->placer($site, $keepTimestamps);
        $placer->placeIn($this->methodFor($layout));
        $placer->describeAll();

        return $placer->plans();
    }

    private function methodFor(Layout|Page $owner): MethodPlan
    {
        return $owner instanceof Layout ? new MethodPlan(MethodKind::Layout, 'layout', $owner) : new MethodPlan(MethodKind::Page, 'page', $owner);
    }

    private function layoutWith(AbstractComponent ...$components): Layout
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        foreach ($components as $index => $component) {
            $this->position($group, $component, ($index + 1) * 10);
        }

        return $layout;
    }

    private function draftOf(DummyPublishableComponent $published): DummyPublishableComponent
    {
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);

        return $draft;
    }
}
