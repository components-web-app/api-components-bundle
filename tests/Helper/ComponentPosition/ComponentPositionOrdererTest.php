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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;

class ComponentPositionOrdererTest extends TestCase
{
    private ComponentPositionOrderer $orderer;

    protected function setUp(): void
    {
        $this->orderer = new ComponentPositionOrderer();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function insertCases(): iterable
    {
        yield '1: after a position with room after it' => ['A0 B10 C20', 'after A', 'A0 N1 B10 C20'];
        yield '2: after a position with no room after it' => ['A0 B1 C2', 'after A', 'A0 N1 B2 C3'];
        yield '3: only the positions it runs into move' => ['A0 B1 C2 D10', 'after A', 'A0 N1 B2 C3 D10'];
        yield '4: before the first position' => ['A0 B1', 'before A', 'N0 A1 B2'];
        yield '5: no anchor' => ['A0 B10', 'end', 'A0 B10 N11'];
        yield '6: after the first of a tie' => ['A5 B5', 'after A', 'A5 N6 B7'];
        yield '7: before the second of a tie' => ['A5 B5', 'before B', 'A5 N6 B7'];
        yield '8: after a position moved by another editor' => ['A0 C20 B21', 'after A', 'A0 N1 C20 B21'];
        yield 'into an empty group' => ['', 'end', 'N0'];
        yield 'after the last position' => ['A0 B10', 'after B', 'A0 B10 N11'];
        yield 'before a position with room before it' => ['A0 B10', 'before B', 'A0 N1 B10'];
    }

    #[DataProvider('insertCases')]
    public function test_an_insert(string $start, string $anchor, string $expected): void
    {
        $positions = $this->positions($start);
        $new = $this->position('N', null);
        $after = $before = null;
        if (str_starts_with($anchor, 'after ')) {
            $after = $positions[substr($anchor, 6)];
        } elseif (str_starts_with($anchor, 'before ')) {
            $before = $positions[substr($anchor, 7)];
        }

        $ordered = $this->orderer->insert(array_values($positions), $new, $after, $before);

        self::assertSame($expected, $this->describe($ordered));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function reorderCases(): iterable
    {
        yield '11: one change' => ['A0 B10 C20', 'B A C', 'B10 A11 C20'];
        yield '12: nothing below 0' => ['A0 B1 C2', 'C A B', 'C2 A3 B4'];
        yield '13: ties removed' => ['A5 B5 C5', 'A B C', 'A5 B6 C7'];
        yield '14: an unlisted position follows its predecessor' => ['A0 H10 B20', 'B A', 'B20 A21 H22'];
        yield '15: a position added since follows its predecessor' => ['A0 B10 N15', 'B A', 'B10 N15 A16'];
        yield 'an unlisted first position stays first' => ['H0 A1 B2', 'B A', 'H0 B2 A3'];
        yield 'unlisted positions follow their listed predecessor together' => ['A0 H1 I2 B3', 'B A', 'B3 A4 H5 I6'];
        yield 'the order unchanged changes nothing' => ['A0 B5 C9', 'A B C', 'A0 B5 C9'];
        yield 'nothing listed changes nothing' => ['A0 B5', '', 'A0 B5'];
        yield 'an empty group' => ['', '', ''];
    }

    #[DataProvider('reorderCases')]
    public function test_a_reorder(string $start, string $order, string $expected): void
    {
        $positions = $this->positions($start);
        $listed = array_map(static fn (string $name) => $positions[$name], preg_split('/\s+/', $order, -1, \PREG_SPLIT_NO_EMPTY));

        $ordered = $this->orderer->reorder(array_values($positions), $listed);

        self::assertSame($expected, $this->describe($ordered));
    }

    /**
     * @return iterable<string, array{list<int|null>, list<int>}>
     */
    public static function sortValueCases(): iterable
    {
        yield 'no positions get nothing' => [[], []];
        yield 'nothing kept starts at 0' => [[null, null, null], [0, 1, 2]];
        yield 'the longest run that fits is kept' => [[10, 0, 20], [10, 11, 20]];
        yield 'a value below its index cannot be kept' => [[2, 0, 1], [2, 3, 4]];
        yield 'positions before the first kept one take the values just below it' => [[null, null, 7], [5, 6, 7]];
        yield 'equal-length runs keep the one ending earliest' => [[5, 5, 5], [5, 6, 7]];
        yield 'a later, longer run is kept' => [[9, 1, 2], [0, 1, 2]];
        yield 'a later run of the same length is not kept' => [[9, 1], [9, 10]];
        yield 'equal-length runs ending at the same place keep the earlier start' => [[3, 2, 9], [3, 4, 9]];
        yield 'a value of exactly its index is kept' => [[null, 1], [0, 1]];
        yield 'a value just below its index is not kept' => [[null, 0], [0, 1]];
        yield 'values equal after subtracting the index are kept together' => [[0, 1, 2], [0, 1, 2]];
        yield 'the gap after the first kept position is filled consecutively' => [[0, null, null, 10], [0, 1, 2, 10]];
        yield 'a run after a non-fitting position' => [[4, null, 0, 6, 7], [3, 4, 5, 6, 7]];
    }

    /**
     * @param list<int|null> $current
     * @param list<int>      $expected
     */
    #[DataProvider('sortValueCases')]
    public function test_sort_values(array $current, array $expected): void
    {
        self::assertSame($expected, $this->orderer->sortValues($current));
    }

    public function test_the_fewest_positions_change(): void
    {
        $current = [3, 40, 41, 42, 1, 2, 50];

        $values = $this->orderer->sortValues($current);

        self::assertSame([3, 40, 41, 42, 43, 44, 50], $values);
        self::assertCount(2, array_diff_assoc($values, $current));
    }

    public function test_every_value_is_unique_and_increasing_and_never_negative(): void
    {
        srand(475);
        for ($run = 0; $run < 200; ++$run) {
            $current = [];
            for ($i = 0, $count = random_int(0, 12); $i < $count; ++$i) {
                $current[] = 0 === random_int(0, 4) ? null : random_int(0, 15);
            }

            $values = $this->orderer->sortValues($current);

            self::assertCount(\count($current), $values);
            foreach ($values as $index => $value) {
                self::assertGreaterThanOrEqual(0, $value);
                if ($index > 0) {
                    self::assertGreaterThan($values[$index - 1], $value);
                }
            }
        }
    }

    public function test_positions_are_sorted_by_sort_value_then_id(): void
    {
        $late = $this->position('L', 9);
        $second = $this->position('S', 2);
        (new \ReflectionProperty(ComponentPosition::class, 'id'))->setValue($second, Uuid::fromString('bbbbbbbb-0000-4000-8000-000000000000'));
        $first = $this->position('F', 2);
        (new \ReflectionProperty(ComponentPosition::class, 'id'))->setValue($first, Uuid::fromString('aaaaaaaa-0000-4000-8000-000000000000'));

        self::assertSame([$first, $second, $late], $this->orderer->sorted(new ArrayCollection([$late, $second, $first])));
    }

    /**
     * @return array<string, ComponentPosition>
     */
    private function positions(string $description): array
    {
        $positions = [];
        foreach (preg_split('/\s+/', $description, -1, \PREG_SPLIT_NO_EMPTY) as $token) {
            preg_match('/^([A-Z])(\d+)$/', $token, $matches);
            $positions[$matches[1]] = $this->position($matches[1], (int) $matches[2]);
        }

        return $positions;
    }

    private function position(string $name, ?int $sortValue): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->sortValue = $sortValue;
        $position->pageDataProperty = $name;

        return $position;
    }

    /**
     * @param list<ComponentPosition> $positions
     */
    private function describe(array $positions): string
    {
        return implode(' ', array_map(static fn (ComponentPosition $position) => $position->pageDataProperty . $position->sortValue, $positions));
    }
}
