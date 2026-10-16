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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\Assignment;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\DeclarationOrderer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ExecutionOrder;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class DeclarationOrdererTest extends TestCase
{
    public function test_a_group_item_is_declared_before_its_owner_and_an_inline_component_is_not_declared(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $inline = $this->plan('inline', $layout);
        $inline->inline = true;
        $panel = $this->plan('panel', $layout);
        $tabs = $this->plan('tabs', $layout);
        $tabs->groups[] = $this->group($panel);
        $layout->groups[] = $this->group($inline, "pageDataPosition(null, 'x')", $tabs);

        $this->declare([$layout], [$tabs, $panel, $inline]);

        self::assertSame(['panel', 'tabs'], $this->names($layout->components));
    }

    public function test_page_data_components_then_the_rest_of_the_scope_are_declared_but_not_an_unplaced_draft_or_another_scope(): void
    {
        $pageData = new MethodPlan(MethodKind::PageData, 'pageData');
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $first = $this->plan('first', $pageData);
        $assigned = $this->plan('assigned', $pageData);
        $pageData->entityAssignments = [new Assignment('title', "'Event'"), new Assignment('component', $assigned)];
        $elsewhere = $this->plan('elsewhere', $layout);
        $unplacedDraft = $this->plan('unplacedDraft', $pageData);
        $unplacedDraft->published = $first;
        $unplacedDraft->uses = 0;

        $this->declare([$layout, $pageData], [$first, $elsewhere, $unplacedDraft, $assigned]);

        self::assertSame(['assigned', 'first'], $this->names($pageData->components));
        self::assertSame(['elsewhere'], $this->names($layout->components));
    }

    public function test_a_related_component_is_declared_first_and_a_cycle_closes_after_both(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $card = $this->plan('card', $layout);
        $teaser = $this->plan('teaser', $layout);
        $layout->groups[] = $this->group($card, $teaser);
        $toTeaser = $this->value('related', 'setRelated');
        $toCard = $this->value('related');

        $this->declare([$layout], [$card, $teaser], [[$card, $toTeaser, $teaser], [$teaser, $toCard, $card]]);

        self::assertSame(['teaser', 'card'], $this->names($layout->components));
        self::assertEquals([new Assignment('related', $teaser, 'setRelated')], $card->assignments);
        self::assertSame([], $teaser->assignments);
        self::assertEquals([new Assignment('related', $card, null, $teaser)], $layout->lateAssignments);
    }

    public function test_a_relation_across_methods_is_assigned_in_the_method_that_runs_later(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = new MethodPlan(MethodKind::Page, 'page');
        $banner = $this->plan('banner', $layout);
        $promo = $this->plan('promo', $page);
        $footer = $this->plan('footer', $layout);
        $value = $this->value('related');

        $this->declare([$layout, $page], [$banner, $promo, $footer], [[$banner, $value, $promo], [$promo, $value, $footer]]);

        self::assertSame([], $banner->assignments);
        self::assertEquals([new Assignment('related', $promo, null, $banner)], $page->lateAssignments);
        self::assertEquals([new Assignment('related', $footer)], $promo->assignments);
        self::assertSame([], $layout->lateAssignments);
    }

    public function test_the_relations_of_a_draft_are_declared_with_its_published_component(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $published = $this->plan('published', $layout);
        $draft = $this->plan('draft', $layout);
        $draft->published = $published;
        $draft->uses = 0;
        $published->draft = $draft;
        $related = $this->plan('related', $layout);
        $layout->groups[] = $this->group($published);

        $this->declare([$layout], [$published, $draft, $related], [[$draft, $this->value('related'), $related]]);

        self::assertSame(['related', 'published'], $this->names($layout->components));
        self::assertEquals([new Assignment('related', $related)], $draft->assignments);
    }

    public function test_a_draft_is_linked_in_the_later_of_the_two_methods(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = new MethodPlan(MethodKind::Page, 'page');
        $earlyPublished = $this->plan('earlyPublished', $layout);
        $lateDraft = $this->plan('lateDraft', $page);
        $latePublished = $this->plan('latePublished', $page);
        $earlyDraft = $this->plan('earlyDraft', $layout);
        $samePublished = $this->plan('samePublished', $layout);
        $sameDraft = $this->plan('sameDraft', $layout);

        (new DeclarationOrderer(new ExecutionOrder([$layout, $page]), [], []))->linkDrafts([
            [$earlyPublished, $lateDraft],
            [$latePublished, $earlyDraft],
            [$samePublished, $sameDraft],
        ]);

        self::assertSame([[$samePublished, $sameDraft]], $layout->draftLinks);
        self::assertSame([[$earlyPublished, $lateDraft], [$latePublished, $earlyDraft]], $page->draftLinks);
    }

    /**
     * @param list<MethodPlan>                                         $methods
     * @param list<ComponentPlan>                                      $plans
     * @param list<array{ComponentPlan, PropertyValue, ComponentPlan}> $relations
     */
    private function declare(array $methods, array $plans, array $relations = []): void
    {
        (new DeclarationOrderer(new ExecutionOrder($methods), $plans, $relations))->declare();
    }

    private function plan(string $name, MethodPlan $scope): ComponentPlan
    {
        $plan = new ComponentPlan(new DummyComponent(), 'DummyComponent', $scope);
        $plan->name = $name;
        $plan->uses = 1;

        return $plan;
    }

    private function group(ComponentPlan|string ...$items): GroupPlan
    {
        $group = new GroupPlan('top', ["'top'"]);
        $group->items = array_values($items);

        return $group;
    }

    private function value(string $property, ?string $setter = null): PropertyValue
    {
        return new PropertyValue(PropertyValue::COMPONENT, $property, null, true, $setter);
    }

    /**
     * @param list<ComponentPlan> $plans
     *
     * @return list<string>
     */
    private function names(array $plans): array
    {
        return array_map(static fn (ComponentPlan $plan): string => $plan->name, $plans);
    }
}
