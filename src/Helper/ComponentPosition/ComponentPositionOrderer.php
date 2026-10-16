<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentPosition;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

final class ComponentPositionOrderer
{
    /**
     * @param iterable<ComponentPosition> $positions
     *
     * @return list<ComponentPosition>
     */
    public function sorted(iterable $positions): array
    {
        $sorted = [...$positions];
        usort($sorted, static fn (ComponentPosition $a, ComponentPosition $b) => [$a->sortValue, (string) $a->getId()] <=> [$b->sortValue, (string) $b->getId()]);

        return $sorted;
    }

    /**
     * @param list<ComponentPosition> $current
     *
     * @return list<ComponentPosition>
     */
    public function insert(array $current, ComponentPosition $position, ?ComponentPosition $after = null, ?ComponentPosition $before = null): array
    {
        $index = \count($current);
        if ($after && false !== ($found = array_search($after, $current, true))) {
            $index = $found + 1;
        } elseif ($before && false !== ($found = array_search($before, $current, true))) {
            $index = $found;
        }
        array_splice($current, $index, 0, [$position]);

        return $this->order($current);
    }

    /**
     * @param list<ComponentPosition> $current
     * @param list<ComponentPosition> $listed
     *
     * @return list<ComponentPosition>
     */
    public function reorder(array $current, array $listed): array
    {
        $leading = [];
        $followers = [];
        $predecessor = null;
        foreach ($current as $position) {
            if (\in_array($position, $listed, true)) {
                $predecessor = $position;
                $followers[spl_object_id($position)] = [];
                continue;
            }
            if (null === $predecessor) {
                $leading[] = $position;
                continue;
            }
            $followers[spl_object_id($predecessor)][] = $position;
        }

        $desired = $leading;
        foreach ($listed as $position) {
            $desired[] = $position;
            array_push($desired, ...($followers[spl_object_id($position)] ?? []));
        }

        return $this->order($desired);
    }

    /**
     * @param list<ComponentPosition> $desired
     *
     * @return list<ComponentPosition>
     */
    public function order(array $desired): array
    {
        $values = $this->sortValues(array_map(static fn (ComponentPosition $position) => $position->sortValue, $desired));
        foreach ($desired as $index => $position) {
            $position->sortValue = $values[$index];
        }

        return $desired;
    }

    /**
     * @param list<int|null> $current
     *
     * @return list<int>
     */
    public function sortValues(array $current): array
    {
        $kept = $this->keptIndexes($current);
        if (!$kept) {
            return array_keys($current);
        }

        $values = [];
        $first = $kept[0];
        for ($index = 0; $index < $first; ++$index) {
            $values[] = $current[$first] - $first + $index;
        }
        $keptIndexes = array_flip($kept);
        for ($index = $first, $count = \count($current); $index < $count; ++$index) {
            $values[] = isset($keptIndexes[$index]) ? $current[$index] : $values[$index - 1] + 1;
        }

        return $values;
    }

    /**
     * @param list<int|null> $current
     *
     * @return list<int>
     */
    private function keptIndexes(array $current): array
    {
        $lengths = [];
        $previous = [];
        $room = [];
        foreach ($current as $index => $value) {
            if (null === $value || $value < $index) {
                continue;
            }
            $room[$index] = $value - $index;
            $lengths[$index] = 1;
            $previous[$index] = null;
            foreach ($lengths as $earlier => $length) {
                if ($earlier < $index && $room[$earlier] <= $room[$index] && $length + 1 > $lengths[$index]) {
                    $lengths[$index] = $length + 1;
                    $previous[$index] = $earlier;
                }
            }
        }
        if (!$lengths) {
            return [];
        }

        $index = array_search(max($lengths), $lengths, true);
        $kept = [];
        while (null !== $index) {
            $kept[] = $index;
            $index = $previous[$index];
        }

        return array_reverse($kept);
    }
}
