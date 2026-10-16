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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentGroupLock;
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
        private readonly ManagerRegistry $registry,
        private readonly ComponentGroupLock $lock,
        private readonly ComponentPositionOrderer $orderer,
        private readonly ComponentPositionIriResolver $positionIriResolver,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$operation instanceof HttpOperation || HttpOperation::METHOD_DELETE === $operation->getMethod()) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        $touched = $this->positionsWritten($data);
        $placing = $touched ? $this->positionsToPlace($touched) : [];
        if (!$placing) {
            return $this->decorated->process($data, $operation, $uriVariables, $context);
        }

        return $this->lock->transactional(function () use ($data, $operation, $uriVariables, $context, $touched, $placing) {
            foreach ($this->byGroup($placing) as [$group, $positions]) {
                $this->place($group, $positions, $touched);
            }

            return $this->decorated->process($data, $operation, $uriVariables, $context);
        });
    }

    /**
     * @param list<ComponentPosition> $positions
     * @param list<ComponentPosition> $touched
     */
    private function place(ComponentGroup $group, array $positions, array $touched): void
    {
        $current = array_values(array_filter(
            $this->lock->lockedPositions($group, $touched),
            static fn (ComponentPosition $position) => !\in_array($position, $positions, true),
        ));

        foreach ($positions as $position) {
            $after = $this->anchor($position, 'after', $current);
            $before = $this->anchor($position, 'before', $current);
            $current = $this->orderer->insert($current, $position, $after, $before);
        }
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

        $constraint = new ComponentPositionConstraint();
        $parameters = ['{{ iri }}' => $iri, '{{ reference }}' => (string) $position->componentGroup?->reference];

        throw new ValidationException(new ConstraintViolationList([new ConstraintViolation(strtr($constraint->anchorNotInGroupMessage, $parameters), $constraint->anchorNotInGroupMessage, $parameters, $position, $property, $iri)]));
    }

    /**
     * @param list<ComponentPosition> $positions
     *
     * @return list<array{ComponentGroup, list<ComponentPosition>}>
     */
    private function byGroup(array $positions): array
    {
        $groups = [];
        foreach ($positions as $position) {
            $group = $position->componentGroup;
            $key = spl_object_id($group);
            $groups[$key] ??= [$group, []];
            $groups[$key][1][] = $position;
        }
        usort($groups, static fn (array $a, array $b) => strcmp((string) $a[0]->getId(), (string) $b[0]->getId()));

        return $groups;
    }

    /**
     * @return list<ComponentPosition>
     */
    private function positionsWritten(mixed $data): array
    {
        if ($data instanceof ComponentPosition) {
            return [$data];
        }

        if ($data instanceof AbstractComponent) {
            return array_values(array_filter(iterator_to_array($data->getComponentPositions(), false), static fn ($position) => $position instanceof ComponentPosition));
        }

        return [];
    }

    /**
     * @param list<ComponentPosition> $touched
     *
     * @return list<ComponentPosition>
     */
    private function positionsToPlace(array $touched): array
    {
        $unitOfWork = $this->unitOfWork();

        return array_values(array_filter($touched, fn (ComponentPosition $position) => null !== $position->componentGroup && $this->needsPlacement($unitOfWork, $position)));
    }

    private function needsPlacement(UnitOfWork $unitOfWork, ComponentPosition $position): bool
    {
        if (null !== $position->after || null !== $position->before) {
            return true;
        }

        if (UnitOfWork::STATE_MANAGED !== $unitOfWork->getEntityState($position) || $unitOfWork->isScheduledForInsert($position)) {
            return true;
        }

        return ($unitOfWork->getOriginalEntityData($position)['componentGroup'] ?? null) !== $position->componentGroup;
    }

    private function unitOfWork(): UnitOfWork
    {
        $entityManager = $this->registry->getManagerForClass(ComponentPosition::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine ORM entity manager manages %s', ComponentPosition::class));
        }

        return $entityManager->getUnitOfWork();
    }
}
