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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageGraph;

class ComponentUsageGraphTest extends TestCase
{
    public function test_a_group_no_component_owns_reaches_only_itself(): void
    {
        self::assertSame(['groups' => ['g1'], 'components' => [], 'pageDataProperties' => []], (new ComponentUsageGraph())->walkUpFrom('g1'));
    }

    public function test_the_walk_climbs_through_each_owner_to_the_groups_holding_it_in_breadth_first_order(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a'], 'g2' => ['b'], 'g3' => ['c']],
            [],
            ['a' => self::held('g2', 'g3'), 'b' => self::held('g4'), 'c' => self::held('g5')],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3', 'g4', 'g5'], 'components' => ['a', 'b', 'c'], 'pageDataProperties' => []], $graph->walkUpFrom('g1'));
    }

    public function test_a_draft_owner_climbs_through_the_groups_holding_its_published_component(): void
    {
        $graph = new ComponentUsageGraph(['g1' => ['draft']], ['draft' => 'published'], ['published' => self::held('g2')]);

        self::assertSame(['groups' => ['g1', 'g2'], 'components' => ['draft', 'published'], 'pageDataProperties' => []], $graph->walkUpFrom('g1'));
    }

    public function test_a_component_reached_twice_is_listed_and_followed_once(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a', 'b'], 'g2' => ['b']],
            ['a' => 'b'],
            ['a' => self::held('g2'), 'b' => self::held('g2', 'g3')],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3'], 'components' => ['a', 'b'], 'pageDataProperties' => []], $graph->walkUpFrom('g1'));
    }

    public function test_a_cycle_terminates_with_every_group_and_component_listed_once(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a'], 'g2' => ['b']],
            [],
            ['a' => self::held('g2'), 'b' => self::held('g1', 'g3')],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3'], 'components' => ['a', 'b'], 'pageDataProperties' => []], $graph->walkUpFrom('g1'));
    }

    public function test_a_dynamic_holder_is_followed_and_its_page_data_property_reported_when_nothing_overrides_it(): void
    {
        $graph = new ComponentUsageGraph(['g1' => ['a']], [], ['a' => [['group' => 'g2', 'pageDataProperty' => 'hero'], ['group' => 'g3', 'pageDataProperty' => null]]]);

        self::assertSame(['groups' => ['g1', 'g2', 'g3'], 'components' => ['a'], 'pageDataProperties' => ['hero']], $graph->walkUpFrom('g1'));
    }

    public function test_a_dynamic_holder_whose_property_is_overridden_is_not_followed_but_still_reported(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a'], 'g2' => ['b']],
            [],
            ['a' => [['group' => 'g2', 'pageDataProperty' => 'hero'], ['group' => 'g3', 'pageDataProperty' => 'aside']], 'b' => self::held('g4')],
        );

        self::assertSame(['groups' => ['g1', 'g3'], 'components' => ['a'], 'pageDataProperties' => ['hero', 'aside']], $graph->walkUpFrom('g1', ['hero']));
    }

    public function test_a_group_reached_through_an_overridden_holder_is_still_reached_through_another(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a']],
            [],
            ['a' => [['group' => 'g2', 'pageDataProperty' => 'hero'], ['group' => 'g2', 'pageDataProperty' => null]]],
        );

        self::assertSame(['g1', 'g2'], $graph->walkUpFrom('g1', ['hero'])['groups']);
    }

    /**
     * @return list<array{group: string, pageDataProperty: null}>
     */
    private static function held(string ...$groups): array
    {
        return array_map(static fn (string $group): array => ['group' => $group, 'pageDataProperty' => null], array_values($groups));
    }
}
