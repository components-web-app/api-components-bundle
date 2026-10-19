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
        self::assertSame(['groups' => ['g1'], 'components' => []], (new ComponentUsageGraph())->walkUpFrom('g1'));
    }

    public function test_the_walk_climbs_through_each_owner_to_the_groups_holding_it_in_breadth_first_order(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a'], 'g2' => ['b'], 'g3' => ['c']],
            [],
            ['a' => ['g2', 'g3'], 'b' => ['g4'], 'c' => ['g5']],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3', 'g4', 'g5'], 'components' => ['a', 'b', 'c']], $graph->walkUpFrom('g1'));
    }

    public function test_a_draft_owner_climbs_through_the_groups_holding_its_published_component(): void
    {
        $graph = new ComponentUsageGraph(['g1' => ['draft']], ['draft' => 'published'], ['published' => ['g2']]);

        self::assertSame(['groups' => ['g1', 'g2'], 'components' => ['draft', 'published']], $graph->walkUpFrom('g1'));
    }

    public function test_a_component_reached_twice_is_listed_and_followed_once(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a', 'b'], 'g2' => ['b']],
            ['a' => 'b'],
            ['a' => ['g2'], 'b' => ['g2', 'g3']],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3'], 'components' => ['a', 'b']], $graph->walkUpFrom('g1'));
    }

    public function test_a_cycle_terminates_with_every_group_and_component_listed_once(): void
    {
        $graph = new ComponentUsageGraph(
            ['g1' => ['a'], 'g2' => ['b']],
            [],
            ['a' => ['g2'], 'b' => ['g1', 'g3']],
        );

        self::assertSame(['groups' => ['g1', 'g2', 'g3'], 'components' => ['a', 'b']], $graph->walkUpFrom('g1'));
    }
}
