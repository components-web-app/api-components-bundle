<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentPosition;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionSortValueHelper;

class ComponentPositionSortValueHelperTest extends TestCase
{
    private ComponentPositionSortValueHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new ComponentPositionSortValueHelper();
    }

    public function test_a_position_with_no_group_and_no_sort_value_is_given_zero(): void
    {
        $position = new ComponentPosition();

        $this->helper->calculateSortValue($position, null);

        self::assertSame(0, $position->sortValue);
    }

    public function test_a_position_with_no_group_keeps_its_sort_value(): void
    {
        $position = new ComponentPosition();
        $position->sortValue = 7;

        $this->helper->calculateSortValue($position, 3);

        self::assertSame(7, $position->sortValue);
    }

    public function test_an_insert_into_an_empty_group_is_given_zero(): void
    {
        $position = $this->createPosition(null);
        $position->setComponentGroup(new ComponentGroup());

        $this->helper->calculateSortValue($position, null);

        self::assertSame(0, $position->sortValue);
    }

    public function test_an_insert_without_a_sort_value_is_appended_after_the_last_position(): void
    {
        [$group, $positions] = $this->createGroup([0, 1, 4]);
        $position = $this->createPosition(null);
        $position->setComponentGroup($group);

        $this->helper->calculateSortValue($position, null);

        self::assertSame(5, $position->sortValue);
        self::assertSame([0, 1, 4], $this->sortValues($positions));
    }

    public function test_an_insert_at_an_occupied_sort_value_shifts_that_position_and_every_later_one(): void
    {
        [$group, $positions] = $this->createGroup([0, 1, 2]);
        $position = $this->createPosition(1);
        $position->setComponentGroup($group);

        $this->helper->calculateSortValue($position, null);

        self::assertSame(1, $position->sortValue);
        self::assertSame([0, 2, 3], $this->sortValues($positions));
    }

    public function test_an_insert_at_a_free_sort_value_shifts_nothing(): void
    {
        [$group, $positions] = $this->createGroup([0, 1, 2]);
        $position = $this->createPosition(5);
        $position->setComponentGroup($group);

        $this->helper->calculateSortValue($position, null);

        self::assertSame(5, $position->sortValue);
        self::assertSame([0, 1, 2], $this->sortValues($positions));
    }

    public function test_an_insert_into_a_gap_shifts_nothing(): void
    {
        [$group, $positions] = $this->createGroup([0, 2, 4]);
        $position = $this->createPosition(1);
        $position->setComponentGroup($group);

        $this->helper->calculateSortValue($position, null);

        self::assertSame(1, $position->sortValue);
        self::assertSame([0, 2, 4], $this->sortValues($positions));
    }

    public function test_a_position_left_at_its_original_sort_value_changes_nothing(): void
    {
        [, $positions] = $this->createGroup([0, 1, 1]);

        $this->helper->calculateSortValue($positions[1], 1);

        self::assertSame([0, 1, 1], $this->sortValues($positions));
    }

    public function test_a_move_to_no_sort_value_changes_no_other_position(): void
    {
        [, $positions] = $this->createGroup([0, 1, 1]);
        $positions[0]->sortValue = null;

        $this->helper->calculateSortValue($positions[0], 0);

        self::assertSame([null, 1, 1], $this->sortValues($positions));
    }

    public function test_moving_a_position_later_shifts_only_the_positions_in_the_moved_range(): void
    {
        [, $positions] = $this->createGroup([0, 1, 2, 3, 4]);
        $positions[1]->sortValue = 3;

        $this->helper->calculateSortValue($positions[1], 1);

        self::assertSame([0, 3, 1, 2, 4], $this->sortValues($positions));
    }

    public function test_moving_a_position_earlier_shifts_only_the_positions_in_the_moved_range(): void
    {
        [, $positions] = $this->createGroup([0, 1, 2, 3, 4]);
        $positions[3]->sortValue = 1;

        $this->helper->calculateSortValue($positions[3], 3);

        self::assertSame([0, 2, 3, 1, 4], $this->sortValues($positions));
    }

    public function test_moving_a_position_in_a_group_with_gaps_keeps_the_gaps(): void
    {
        [, $positions] = $this->createGroup([0, 5, 10]);
        $positions[0]->sortValue = 7;

        $this->helper->calculateSortValue($positions[0], 0);

        self::assertSame([7, 4, 10], $this->sortValues($positions));
    }

    public function test_moving_a_position_earlier_in_a_group_with_gaps_shifts_every_position_in_the_moved_range(): void
    {
        [, $positions] = $this->createGroup([0, 5, 10]);
        $positions[2]->sortValue = 0;

        $this->helper->calculateSortValue($positions[2], 10);

        self::assertSame([1, 6, 0], $this->sortValues($positions));
    }

    public function test_moving_a_position_into_a_group_at_an_occupied_sort_value_resolves_to_unique_values(): void
    {
        [$group, $positions] = $this->createGroup([0, 1, 2]);
        $moved = $this->createPosition(1);
        $moved->setComponentGroup($group);

        $this->helper->calculateSortValue($moved, 0);

        self::assertSame(1, $moved->sortValue);
        self::assertSame([0, 2, 3], $this->sortValues($positions));
    }

    public function test_moving_a_position_later_in_a_group_holding_a_duplicate_resolves_to_unique_values(): void
    {
        [, $positions] = $this->createGroup([0, 1, 1, 3]);
        $positions[0]->sortValue = 3;

        $this->helper->calculateSortValue($positions[0], 0);

        self::assertSame([3, 0, 1, 2], $this->sortValues($positions));
    }

    public function test_moving_a_position_earlier_in_a_group_holding_a_duplicate_resolves_to_unique_values(): void
    {
        [, $positions] = $this->createGroup([0, 1, 1, 2]);
        $positions[3]->sortValue = 0;

        $this->helper->calculateSortValue($positions[3], 2);

        self::assertSame([1, 2, 3, 0], $this->sortValues($positions));
    }

    public function test_a_repair_keeps_the_moved_position_at_its_sort_value_when_the_cascade_reaches_it(): void
    {
        [$group, $positions] = $this->createGroup([0, 0, 0]);
        $moved = $this->createPosition(1);
        $moved->setComponentGroup($group);

        $this->helper->calculateSortValue($moved, 5);

        self::assertSame(1, $moved->sortValue);
        self::assertSame([0, 2, 3], $this->sortValues($positions));
    }

    public function test_a_repair_keeps_the_relative_order_of_the_other_positions(): void
    {
        [$group, $positions] = $this->createGroup([4, 2, 2, 0]);
        $moved = $this->createPosition(9);
        $moved->setComponentGroup($group);

        $this->helper->calculateSortValue($moved, 20);

        self::assertSame(9, $moved->sortValue);
        self::assertSame([4, 2, 3, 0], $this->sortValues($positions));
    }

    public function test_a_repair_changes_no_position_that_does_not_collide(): void
    {
        [, $positions] = $this->createGroup([0, 1, 2, 2, 7]);
        $positions[0]->sortValue = 1;

        $this->helper->calculateSortValue($positions[0], 0);

        self::assertSame([1, 0, 2, 3, 7], $this->sortValues($positions));
    }

    /**
     * @param list<int> $sortValues
     *
     * @return array{0: ComponentGroup, 1: list<ComponentPosition>}
     */
    private function createGroup(array $sortValues): array
    {
        $group = new ComponentGroup();
        $positions = [];
        foreach ($sortValues as $sortValue) {
            $position = $this->createPosition($sortValue);
            $position->setComponentGroup($group);
            $group->addComponentPosition($position);
            $positions[] = $position;
        }

        return [$group, $positions];
    }

    private function createPosition(?int $sortValue): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->sortValue = $sortValue;
        (new \ReflectionProperty(ComponentPosition::class, 'id'))->setValue($position, Uuid::uuid4());

        return $position;
    }

    /**
     * @param list<ComponentPosition> $positions
     *
     * @return list<int|null>
     */
    private function sortValues(array $positions): array
    {
        return array_map(static fn (ComponentPosition $position) => $position->sortValue, $positions);
    }
}
