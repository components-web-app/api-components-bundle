<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
final class ComponentPositionPlacementStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private readonly ProcessorInterface $decorated,
        private readonly ComponentPositionOrderer $orderer,
        private readonly ComponentPositionIriResolver $positionIriResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof HttpOperation && HttpOperation::METHOD_DELETE !== $operation->getMethod()) {
            foreach ($this->positionsWritten($data) as $position) {
                $this->place($position);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function place(ComponentPosition $position): void
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

    /**
     * @return iterable<ComponentPosition>
     */
    private function positionsWritten(mixed $data): iterable
    {
        if ($data instanceof ComponentPosition) {
            return [$data];
        }

        return $data instanceof AbstractComponent ? $data->getComponentPositions() : [];
    }
}
