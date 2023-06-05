<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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
            if ($moveTo === $originalSortValue) {
                return;
            }

            $positionIsSame = static function (ComponentPosition $posA, ComponentPosition $posB) {
                return $posA->getId() === $posB->getId();
            };

            if ($moveTo > $originalSortValue) {
                // value increased
                foreach ($sortCollection as $existingComponentPosition) {
                    if ($positionIsSame($existingComponentPosition, $componentPosition)) {
                        continue;
                    }
                    if (
                        $existingComponentPosition->sortValue > $originalSortValue
                        && $existingComponentPosition->sortValue <= $moveTo
                    ) {
                        --$existingComponentPosition->sortValue;
                    }
                }

                return;
            }

            // value decreased
            foreach ($sortCollection as $existingComponentPosition) {
                if ($positionIsSame($existingComponentPosition, $componentPosition)) {
                    continue;
                }
                if (
                    $existingComponentPosition->sortValue < $originalSortValue
                    && $existingComponentPosition->sortValue >= $moveTo
                ) {
                    ++$existingComponentPosition->sortValue;
                }
            }

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

        foreach ($sortCollection as $existingComponentPosition) {
            if ($existingComponentPosition->sortValue >= $componentPosition->sortValue) {
                ++$existingComponentPosition->sortValue;
            }
        }
    }
}
