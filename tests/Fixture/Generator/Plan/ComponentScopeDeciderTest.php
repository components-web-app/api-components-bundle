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
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentScopeDecider;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class ComponentScopeDeciderTest extends TestCase
{
    public function test_a_component_used_once_with_nothing_to_set_is_inline(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $plan = $this->placed($layout);

        (new ComponentScopeDecider([$layout]))->decide([$plan], []);

        self::assertFalse($plan->property);
        self::assertTrue($plan->inline);
        self::assertSame($layout, $plan->scope);
    }

    public function test_a_component_used_twice_in_one_method_is_a_local_variable(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $plan = $this->placed($layout, uses: 2);

        (new ComponentScopeDecider([$layout]))->decide([$plan], []);

        self::assertFalse($plan->property);
        self::assertFalse($plan->inline);
    }

    public function test_a_component_used_in_two_methods_is_a_property(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = new MethodPlan(MethodKind::Page, 'page');
        $plan = $this->placed($layout);
        $plan->scopes[spl_object_id($page)] = $page;

        (new ComponentScopeDecider([$layout, $page]))->decide([$plan], []);

        self::assertTrue($plan->property);
        self::assertFalse($plan->inline);
        self::assertSame($layout, $plan->scope);
    }

    public function test_a_pair_across_methods_makes_both_properties_and_a_pair_in_one_method_makes_neither(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = new MethodPlan(MethodKind::Page, 'page');
        $banner = $this->placed($layout);
        $promo = $this->placed($page);
        $card = $this->placed($layout);
        $teaser = $this->placed($layout);

        (new ComponentScopeDecider([$layout, $page]))->decide([$banner, $promo, $card, $teaser], [[$banner, $promo], [$card, $teaser]]);

        self::assertTrue($banner->property);
        self::assertTrue($promo->property);
        self::assertFalse($card->property);
        self::assertFalse($teaser->property);
    }

    public function test_a_pair_with_one_property_across_methods_makes_the_other_a_property_too(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = new MethodPlan(MethodKind::Page, 'page');
        $shared = $this->placed($layout);
        $shared->scopes[spl_object_id($page)] = $page;
        $related = $this->placed($page);

        (new ComponentScopeDecider([$layout, $page]))->decide([$shared, $related], [[$shared, $related]]);

        self::assertTrue($related->property);
    }

    public function test_a_component_paired_with_one_that_moved_is_decided_again(): void
    {
        [$parent, $child, $other] = $this->nestedPages();
        $moved = $this->placed($child);
        $moved->scopes[spl_object_id($other)] = $other;
        $partner = $this->placed($child);

        (new ComponentScopeDecider([$parent, $other]))->decide([$moved, $partner], [[$moved, $partner]]);

        self::assertTrue($partner->property);
        self::assertSame($parent, $partner->scope);
    }

    public function test_a_property_placed_nowhere_that_is_not_a_draft_moves_like_any_other(): void
    {
        [$parent, $child, $other] = $this->nestedPages();
        $plan = $this->placed($child, uses: 0);
        $plan->property = true;

        (new ComponentScopeDecider([$parent, $other]))->decide([$plan], []);

        self::assertSame($parent, $plan->scope);
    }

    public function test_a_property_first_used_in_a_nested_method_moves_to_its_top_level_method(): void
    {
        [$parent, $child, $other] = $this->nestedPages();
        $plan = $this->placed($child);
        $plan->scopes[spl_object_id($other)] = $other;
        $local = $this->placed($child, uses: 2);

        (new ComponentScopeDecider([$parent, $other]))->decide([$plan, $local], []);

        self::assertSame($parent, $plan->scope);
        self::assertSame([spl_object_id($child), spl_object_id($other), spl_object_id($parent)], array_keys($plan->scopes));
        self::assertSame($child, $local->scope);
    }

    public function test_a_property_that_owns_a_group_stays_in_a_nested_page_method(): void
    {
        [$parent, $child, $other] = $this->nestedPages();
        $plan = $this->placed($child);
        $plan->scopes[spl_object_id($other)] = $other;
        $plan->groups[] = new GroupPlan('panels', ["'panels'"]);

        (new ComponentScopeDecider([$parent, $other]))->decide([$plan], []);

        self::assertTrue($plan->property);
        self::assertSame($child, $plan->scope);
    }

    public function test_a_property_that_owns_a_group_under_page_data_moves_with_the_components_its_group_declares_there(): void
    {
        $page = new MethodPlan(MethodKind::Page, 'page');
        $pageData = new MethodPlan(MethodKind::PageData, 'pageData');
        $nested = new MethodPlan(MethodKind::Page, 'nested');
        $pageData->children = [$nested];
        $tabs = $this->placed($nested);
        $tabs->scopes[spl_object_id($page)] = $page;
        $panel = $this->placed($nested);
        $sharedPanel = $this->placed($nested, uses: 2);
        $elsewhere = $this->placed($page);
        $group = new GroupPlan('panels', ["'panels'"]);
        $group->items = [$panel, "pageDataPosition(null, 'x')", $sharedPanel, $elsewhere];
        $tabs->groups[] = $group;

        (new ComponentScopeDecider([$page, $pageData]))->decide([$tabs, $panel, $sharedPanel, $elsewhere], []);

        self::assertSame($pageData, $tabs->scope);
        self::assertSame($pageData, $panel->scope);
        self::assertSame([spl_object_id($pageData)], array_keys($panel->scopes));
        self::assertTrue($panel->inline);
        self::assertSame($pageData, $sharedPanel->scope);
        self::assertSame([spl_object_id($nested), spl_object_id($pageData)], array_keys($sharedPanel->scopes));
        self::assertSame($page, $elsewhere->scope);
    }

    public function test_a_draft_moves_with_its_published_component_and_an_unplaced_draft_is_never_moved_itself(): void
    {
        [$parent, $child, $other] = $this->nestedPages();
        $published = $this->placed($child);
        $published->scopes[spl_object_id($other)] = $other;
        $draft = new ComponentPlan(new DummyComponent(), 'DummyComponent', $child);
        $draft->published = $published;
        $draft->property = true;
        $published->draft = $draft;
        $unplaced = new ComponentPlan(new DummyComponent(), 'DummyComponent', $child);
        $unplaced->published = $this->placed($child);
        $unplaced->property = true;

        (new ComponentScopeDecider([$parent, $other]))->decide([$published, $draft, $unplaced], []);

        self::assertSame($parent, $published->scope);
        self::assertSame($parent, $draft->scope);
        self::assertSame($child, $unplaced->scope);
        self::assertFalse($draft->inline);
    }

    /**
     * @return array{MethodPlan, MethodPlan, MethodPlan}
     */
    private function nestedPages(): array
    {
        $parent = new MethodPlan(MethodKind::Page, 'parent');
        $child = new MethodPlan(MethodKind::Page, 'child');
        $parent->children = [$child];

        return [$parent, $child, new MethodPlan(MethodKind::Page, 'other')];
    }

    private function placed(MethodPlan $scope, int $uses = 1): ComponentPlan
    {
        $plan = new ComponentPlan(new DummyComponent(), 'DummyComponent', $scope);
        $plan->uses = $uses;
        $plan->scopes[spl_object_id($scope)] = $scope;

        return $plan;
    }
}
