<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Factory\ComponentPosition;

use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;

final class ComponentGroupOrderFactory
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ListedComponentPositions $listedComponentPositions,
    ) {
    }

    /**
     * @param iterable<ComponentPosition> $positions
     */
    public function create(ComponentGroup $group, iterable $positions): ComponentGroupOrder
    {
        $sorted = [];
        foreach ($positions as $position) {
            if ($this->listedComponentPositions->isListed($group, $position)) {
                $sorted[] = $position;
            }
        }
        usort($sorted, static fn (ComponentPosition $a, ComponentPosition $b) => [$a->sortValue, (string) $a->getId()] <=> [$b->sortValue, (string) $b->getId()]);

        $sortValues = [];
        foreach ($sorted as $position) {
            $sortValues[$this->iriConverter->getIriFromResource($position)] = (int) $position->sortValue;
        }

        return new ComponentGroupOrder((string) $group->getId(), $group, $sortValues);
    }
}
