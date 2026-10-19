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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ScaffoldRenderer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\Assignment;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlanner;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteReader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class ScaffoldPlannerTest extends TestCase
{
    use PlannerTestTrait;

    private const string NOW = '2050-01-01T00:00:00+00:00';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function test_a_component_in_one_position_with_nothing_to_set_is_inline(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), new DummyComponent(), 10);

        $component = $this->onlyItem($this->plan());

        self::assertTrue($component->inline);
        self::assertSame('new DummyComponent()', ScaffoldRenderer::expression($component));
        self::assertSame([], $this->plan()->methods[0]->components);
    }

    public function test_a_component_in_two_positions_of_one_method_is_a_local_variable(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $component = new DummyComponent();
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $component, 10);
        $this->position($this->group('bottom_/_/layouts/main', '/_/layouts/main', $layout), $component, 10);

        $plan = $this->plan();
        $declared = $plan->methods[0]->components;

        self::assertCount(1, $declared);
        self::assertFalse($declared[0]->inline);
        self::assertFalse($declared[0]->property);
        self::assertSame('$mainBottomDummyComponent', ScaffoldRenderer::expression($declared[0]));
        self::assertSame([], $plan->properties);
    }

    public function test_a_component_used_in_two_methods_is_a_property_declared_in_the_first(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $link = new DummyNavigationLink();
        $link->label = 'Contact us';
        $this->position($this->group('header_/_/layouts/main', '/_/layouts/main', $layout), $link, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $link, 10);

        $plan = $this->plan();

        self::assertCount(1, $plan->properties);
        self::assertSame('$this->contactUsDummyNavigationLink', ScaffoldRenderer::expression($plan->properties[0]));
        self::assertSame([$plan->properties[0]], $plan->methods[0]->components);
        self::assertSame([], $plan->methods[1]->components);
    }

    public function test_a_component_used_only_by_nested_methods_is_declared_in_the_top_level_method_above_the_first(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $alpha = $this->page('alpha', $layout, $this->route('/alpha', 'alpha'));
        $alphaChild = $this->page('alpha-child', $layout, $this->route('/alpha/child', 'alpha-child'));
        $alphaChild->setParentPage($alpha);
        $event = new PageData();
        $event->setTitle('Event');
        $this->route('/event', 'event')->setPageData($event);
        $this->repositories[AbstractPageData::class][] = $event;
        $eventChild = $this->page('event-child', $layout, $this->route('/event/child', 'event-child'));
        $eventChild->setParentPageData($event);
        $link = new DummyNavigationLink();
        $link->label = 'Shared';
        $this->position($this->group('primary_/_/pages/alpha-child', '/_/pages/alpha-child', $alphaChild), $link, 10);
        $this->position($this->group('primary_/_/pages/event-child', '/_/pages/event-child', $eventChild), $link, 10);

        $plan = $this->plan();
        $methods = $this->methodsByName($plan->methods);

        self::assertSame(['mainLayout', 'alphaPage', 'alphaChildPage', 'eventPageData', 'eventChildPage'], array_keys($methods));
        self::assertSame([], $methods['alphaChildPage']->components);
        self::assertSame([], $methods['eventChildPage']->components);
        self::assertCount(1, $methods['eventPageData']->components);
        self::assertTrue($methods['eventPageData']->components[0]->property);
    }

    public function test_a_component_that_owns_a_group_stays_declared_in_the_nested_method_that_runs_first(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $first = $this->page('first', $layout, $this->route('/parent/first', 'first'));
        $first->setParentPage($parent);
        $second = $this->page('second', $layout, $this->route('/parent/second', 'second'));
        $second->setParentPage($parent);
        $tabs = new DummyNavigationLink();
        $tabs->label = 'Tabs';
        $this->ownGroup($tabs, 'panels');
        $this->position($this->group('primary_/_/pages/first', '/_/pages/first', $first), $tabs, 10);
        $this->position($this->group('primary_/_/pages/second', '/_/pages/second', $second), $tabs, 10);

        $methods = $this->methodsByName($this->plan()->methods);

        self::assertSame([], $methods['parentPage']->components);
        self::assertCount(1, $methods['firstPage']->components);
        self::assertTrue($methods['firstPage']->components[0]->property);
    }

    public function test_a_component_that_owns_a_group_and_runs_first_under_page_data_is_declared_with_its_group_in_the_top_level_method(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $loose = new PageData();
        $loose->setTitle('Loose');
        $this->repositories[AbstractPageData::class][] = $loose;
        $first = $this->page('first', $layout, $this->route('/first', 'first'));
        $first->setParentPageData($loose);
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $second = $this->page('second', $layout, $this->route('/parent/second', 'second'));
        $second->setParentPage($parent);
        $tabs = new DummyNavigationLink();
        $tabs->label = 'Tabs';
        $panel = new DummyComponent();
        $this->position($this->ownGroup($tabs, 'panels'), $panel, 10);
        $this->position($this->group('primary_/_/pages/first', '/_/pages/first', $first), $tabs, 10);
        $this->position($this->group('primary_/_/pages/second', '/_/pages/second', $second), $tabs, 10);

        $methods = $this->methodsByName($this->plan()->methods);

        self::assertSame([], $methods['firstPage']->components);
        self::assertSame([], $methods['secondPage']->components);
        self::assertCount(1, $methods['loosePageData']->components);
        $declared = $methods['loosePageData']->components[0];
        self::assertTrue($declared->property);
        self::assertSame('$this->tabsDummyNavigationLink', ScaffoldRenderer::expression($declared));
        $item = $declared->groups[0]->items[0];
        self::assertInstanceOf(ComponentPlan::class, $item);
        self::assertSame($methods['loosePageData'], $item->scope);
        self::assertTrue($item->inline);
    }

    public function test_a_related_component_is_declared_first_and_a_cycle_closes_after_both(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $teaser = new DummyComponentWithRelated();
        $teaser->name = 'Teaser';
        $card->related = $teaser;
        $teaser->related = $card;
        $this->position($group, $card, 10);
        $this->position($group, $teaser, 20);

        $method = $this->plan()->methods[0];

        self::assertSame(['teaserDummyComponentWithRelated', 'cardDummyComponentWithRelated'], $this->names($method->components));
        self::assertSame('$teaserDummyComponentWithRelated', $this->assignedValue($method->components[1], 'related'));
        self::assertCount(1, $method->lateAssignments);
        self::assertSame('$teaserDummyComponentWithRelated', ScaffoldRenderer::expression($this->target($method->lateAssignments[0])));
        self::assertSame($method->components[1], $method->lateAssignments[0]->value);
    }

    public function test_components_related_across_methods_are_both_properties_and_assigned_in_the_later_method(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $banner = new DummyComponentWithRelated();
        $banner->name = 'Banner';
        $promo = new DummyComponentWithRelated();
        $promo->name = 'Promo';
        $banner->related = $promo;
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $banner, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $promo, 10);

        $plan = $this->plan();

        self::assertCount(2, $plan->properties);
        self::assertSame([], $plan->methods[0]->lateAssignments);
        self::assertCount(1, $plan->methods[1]->lateAssignments);
        self::assertSame('$this->bannerDummyComponentWithRelated', ScaffoldRenderer::expression($this->target($plan->methods[1]->lateAssignments[0])));
    }

    public function test_a_component_related_to_a_component_placed_nowhere_declares_it_in_the_same_method(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $card->related = new DummyComponent();
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $card, 10);

        $method = $this->plan()->methods[0];

        self::assertSame(['cardRelatedDummyComponent', 'cardDummyComponentWithRelated'], $this->names($method->components));
        self::assertSame('$cardRelatedDummyComponent', $this->assignedValue($method->components[1], 'related'));
    }

    public function test_a_draft_is_attached_to_its_published_component_and_named_after_it(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $published = new DummyPublishableComponent();
        $published->reference = 'Welcome to CWA';
        $draft = new DummyPublishableComponent();
        $draft->reference = 'Welcome to CWA 2.0';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $published, 10);

        $method = $this->plan()->methods[0];

        self::assertCount(1, $method->components);
        self::assertSame('welcomeCwaDummyPublishableComponent', $method->components[0]->name);
        self::assertSame('welcomeCwaDummyPublishableComponentDraft', $method->components[0]->draft?->name);
        self::assertSame($method->components[0], $method->components[0]->draft->published);
    }

    public function test_a_related_draft_is_planned_as_its_published_component_and_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $published = new DummyPublishableComponent();
        $published->reference = 'Live';
        $draft = new DummyPublishableComponent();
        $draft->reference = 'Pending';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $card->related = $draft;
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->position($group, $card, 10);
        $this->position($group, $published, 20);

        $plan = $this->plan();
        $method = $plan->methods[0];

        self::assertSame(['liveDummyPublishableComponent', 'cardDummyComponentWithRelated'], $this->names($method->components));
        [$live, $cardPlan] = $method->components;
        self::assertSame($card, $cardPlan->component);
        self::assertSame('liveDummyPublishableComponentDraft', $live->draft?->name);
        self::assertSame($draft, $live->draft->component);
        self::assertSame(['The component field ' . DummyComponentWithRelated::class . '::$related holds a draft of a published component, which is generated as the published component a write would store (1 component).'], $plan->notReproduced);
    }

    public function test_a_draft_related_in_another_method_relates_both_published_component_properties(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $published = new DummyPublishableComponent();
        $published->reference = 'Live';
        $draft = new DummyPublishableComponent();
        $draft->reference = 'Pending';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $card->related = $draft;
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $card, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $published, 10);

        $plan = $this->plan();

        self::assertSame(['cardDummyComponentWithRelated', 'liveDummyPublishableComponent'], $this->names($plan->properties));
    }

    public function test_a_draft_in_a_position_of_its_own_is_planned_as_its_published_component_and_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $published = new DummyPublishableComponent();
        $published->reference = 'Live';
        $draft = new DummyPublishableComponent();
        $draft->reference = 'Pending';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->position($group, $published, 10);
        $this->position($group, $draft, 20);

        $plan = $this->plan();
        $method = $plan->methods[0];

        self::assertSame(['liveDummyPublishableComponent'], $this->names($method->components));
        self::assertSame('liveDummyPublishableComponentDraft', $method->components[0]->draft?->name);
        self::assertSame(['A position holds a draft of a published component, which is generated as the published component a write would store (1 entity).'], $plan->notReproduced);
    }

    public function test_a_group_owned_by_a_component_is_planned_with_its_positions_in_the_owner_method(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $tabs = new DummyComponent();
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $tabs, 10);
        $panel = new DummyNavigationLink();
        $panel->label = 'Panel';
        $this->position($this->ownGroup($tabs, 'panels'), $panel, 10);

        $method = $this->plan()->methods[0];

        self::assertSame(['panelDummyNavigationLink', 'mainTopDummyComponent'], $this->names($method->components));
        self::assertSame('panels', $method->components[1]->groups[0]->name);
    }

    public function test_methods_of_children_follow_their_parent(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $child = $this->page('child', $layout, $this->route('/parent/child', 'child'));
        $child->setParentPage($parent);
        $this->page('other', $layout, $this->route('/z', 'z'));

        $plan = $this->plan();

        self::assertSame(['mainLayout', 'parentPage', 'otherPage'], array_map(static fn (MethodPlan $method): string => $method->name, $plan->methods));
        self::assertSame(['mainLayout', 'parentPage', 'childPage', 'otherPage'], array_map(static fn (MethodPlan $method): string => $method->name, ScaffoldPlan::inFileOrder($plan->methods)));
    }

    public function test_a_past_publish_date_is_published_at_load_time_unless_timestamps_are_kept(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $published, 10);

        self::assertTrue($this->onlyItem($this->plan())->publishNow);
        self::assertSame("new \\DateTimeImmutable('2020-01-01T00:00:00+00:00')", $this->onlyItem($this->plan(keepTimestamps: true))->publishAt);
    }

    public function test_what_methods_cannot_reproduce_is_reported_before_what_components_cannot(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $this->page('about', $layout, $about);
        $draft = $this->route('/old', 'old');
        $draft->setRedirect($about);
        $draft->setLiveAt(null);
        $this->repositories[Route::class] = [$draft];
        $link = new DummyNavigationLink();
        $link->route = $this->route('/elsewhere', 'elsewhere');
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $link, 10);

        $notReproduced = $this->plan()->notReproduced;

        self::assertCount(2, $notReproduced);
        self::assertSame('The redirect from "/old" goes live at no date (a draft), but a fixture redirect is always live.', $notReproduced[0]);
        self::assertStringContainsString(DummyNavigationLink::class . '::$route refers to', $notReproduced[1]);
    }

    private function plan(bool $keepTimestamps = false): ScaffoldPlan
    {
        $registry = $this->registry();
        $arguments = [$registry, $this->iriConverter(), $this->stateReader($registry), $this->storedFileExporter(), new ImportCollector('SiteScaffold'), new PhpValueExporter(), new \DateTimeImmutable(self::NOW)];
        $planner = $keepTimestamps ? new ScaffoldPlanner(...$arguments, keepTimestamps: true) : new ScaffoldPlanner(...$arguments);

        return $planner->plan((new SiteReader($registry))->read());
    }

    private function target(Assignment $assignment): ComponentPlan
    {
        self::assertNotNull($assignment->target);

        return $assignment->target;
    }

    private function onlyItem(ScaffoldPlan $plan): ComponentPlan
    {
        $item = $plan->methods[0]->groups[0]->items[0];
        self::assertInstanceOf(ComponentPlan::class, $item);

        return $item;
    }

    private function assignedValue(ComponentPlan $plan, string $property): ?string
    {
        foreach ($plan->assignments as $assignment) {
            if ($property === $assignment->property) {
                return $assignment->value instanceof ComponentPlan ? ScaffoldRenderer::expression($assignment->value) : $assignment->value;
            }
        }

        return null;
    }
}
