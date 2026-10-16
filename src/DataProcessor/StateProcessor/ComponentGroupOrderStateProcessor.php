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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<mixed, ComponentGroupOrder|null>
 */
final class ComponentGroupOrderStateProcessor implements ProcessorInterface
{
    public const string NOT_IN_GROUP_MESSAGE = 'The position `{{ iri }}` is not in the component group `{{ reference }}`.';
    public const string REPEATED_MESSAGE = 'The position `{{ iri }}` is listed more than once.';

    /**
     * @param ProviderInterface<ComponentGroupOrder> $orderProvider
     */
    public function __construct(
        private readonly ComponentGroupRepository $componentGroupRepository,
        private readonly ComponentPositionOrderer $orderer,
        private readonly ComponentPositionIriResolver $positionIriResolver,
        private readonly ProviderInterface $orderProvider,
        private readonly ManagerRegistry $registry,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ComponentGroupOrder
    {
        $id = $uriVariables['id'] ?? null;
        $group = \is_string($id) ? $this->componentGroupRepository->findOneByIdOrReference($id) : null;
        if (!$group) {
            throw new NotFoundHttpException('Not Found');
        }

        $current = $this->orderer->sorted($group->componentPositions);
        $iris = $data instanceof ComponentGroupOrder ? $data->componentPositions : [];
        $this->orderer->reorder($current, $this->listedPositions($group, $current, $iris));
        $this->registry->getManagerForClass(ComponentPosition::class)?->flush();

        return $this->orderProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * @param list<ComponentPosition> $current
     * @param array<string>           $iris
     *
     * @return list<ComponentPosition>
     */
    private function listedPositions(ComponentGroup $group, array $current, array $iris): array
    {
        $listed = [];
        $violations = new ConstraintViolationList();
        foreach (array_values($iris) as $index => $iri) {
            $position = $this->positionIriResolver->resolve($iri);
            if (!$position && $this->positionIriResolver->isMissing($iri)) {
                continue;
            }

            if ($position && \in_array($position, $listed, true)) {
                $violations->add($this->violation(self::REPEATED_MESSAGE, $group, $index, $iri));
                continue;
            }

            if (!$position || !\in_array($position, $current, true)) {
                $violations->add($this->violation(self::NOT_IN_GROUP_MESSAGE, $group, $index, $iri));
                continue;
            }

            $listed[] = $position;
        }

        if (\count($violations)) {
            throw new ValidationException($violations);
        }

        return $listed;
    }

    private function violation(string $template, ComponentGroup $group, int $index, string $iri): ConstraintViolation
    {
        $parameters = ['{{ iri }}' => $iri, '{{ reference }}' => (string) $group->reference];

        return new ConstraintViolation(strtr($template, $parameters), $template, $parameters, $group, \sprintf('componentPositions[%d]', $index), $iri);
    }
}
