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

use ApiPlatform\Validator\Exception\ValidationException;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ComponentPositionPlacer
{
    public function __construct(
        private readonly ComponentPositionOrderer $orderer,
        private readonly ComponentPositionIriResolver $positionIriResolver,
    ) {
    }

    public function place(ComponentPosition $position): void
    {
        $group = $position->componentGroup;
        if (!$group || ($group->componentPositions->contains($position) && null === $position->after && null === $position->before)) {
            return;
        }

        $current = array_values(array_filter(
            $this->orderer->sorted($group->componentPositions),
            static fn (ComponentPosition $other) => $other !== $position,
        ));
        $this->orderer->insert($current, $position, $this->anchor($position, 'after', $current), $this->anchor($position, 'before', $current));
        $group->addComponentPosition($position);
    }

    /**
     * @param list<ComponentPosition> $current
     */
    private function anchor(ComponentPosition $position, string $property, array $current): ?ComponentPosition
    {
        $iri = $position->{$property};
        if (null === $iri) {
            return null;
        }

        $anchor = $this->positionIriResolver->resolve($iri);
        if ($anchor && \in_array($anchor, $current, true)) {
            return $anchor;
        }

        $template = (new ComponentPositionConstraint())->anchorNotInGroupMessage;
        $parameters = ['{{ iri }}' => $iri, '{{ reference }}' => (string) $position->componentGroup?->reference];

        throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(strtr($template, $parameters), $template, $parameters, $position, $property, $iri)]));
    }
}
