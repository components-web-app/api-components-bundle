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

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionSortValueHelper
{
    public function calculateSortValue(ComponentPosition $componentPosition, ?int $originalSortValue): void
    {
        $sortCollection = $componentPosition->getSortCollection();
        $sortValueSet = null !== $componentPosition->sortValue;
        if (!$sortCollection) {
            if (!$sortValueSet) {
                $componentPosition->setSortValue(0);
            }

            return;
        }

        if (null !== $originalSortValue) {
            $moveTo = $componentPosition->sortValue;

            if (null === $moveTo || $moveTo === $originalSortValue) {
                return;
            }

            $otherPositions = [];
            foreach ($sortCollection as $existingComponentPosition) {
                if ($existingComponentPosition->getId() !== $componentPosition->getId()) {
                    $otherPositions[] = $existingComponentPosition;
                }
            }

            foreach ($otherPositions as $existingComponentPosition) {
                if ($moveTo > $originalSortValue) {
                    if ($existingComponentPosition->sortValue > $originalSortValue && $existingComponentPosition->sortValue <= $moveTo) {
                        --$existingComponentPosition->sortValue;
                    }
                } elseif ($existingComponentPosition->sortValue < $originalSortValue && $existingComponentPosition->sortValue >= $moveTo) {
                    ++$existingComponentPosition->sortValue;
                }
            }

            $this->resolveMoveCollisions($otherPositions, $moveTo);

            return;
        }

        if (!$sortValueSet) {
            /** @var ComponentPosition|null $lastPosition */
            $lastPosition = $sortCollection->last();
            if ($lastPosition) {
                $nextValue = $lastPosition->sortValue + 1;
                $componentPosition->setSortValue($nextValue);
            } else {
                $componentPosition->setSortValue(0);
            }
        }

        $hasCollision = false;
        foreach ($sortCollection as $existingComponentPosition) {
            if ($existingComponentPosition->sortValue === $componentPosition->sortValue) {
                $hasCollision = true;
                break;
            }
        }

        if ($hasCollision) {
            foreach ($sortCollection as $existingComponentPosition) {
                if ($existingComponentPosition->sortValue >= $componentPosition->sortValue) {
                    ++$existingComponentPosition->sortValue;
                }
            }
        }
    }

    /**
     * @param list<ComponentPosition> $otherPositions
     */
    private function resolveMoveCollisions(array $otherPositions, int $moveTo): void
    {
        usort($otherPositions, static fn (ComponentPosition $a, ComponentPosition $b) => $a->sortValue <=> $b->sortValue);

        $previousSortValue = null;
        foreach ($otherPositions as $position) {
            $sortValue = null === $previousSortValue ? $position->sortValue : max($position->sortValue, $previousSortValue + 1);
            if ($sortValue === $moveTo) {
                ++$sortValue;
            }
            $position->sortValue = $sortValue;
            $previousSortValue = $sortValue;
        }
    }
}
