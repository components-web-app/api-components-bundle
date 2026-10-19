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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionPlacer;

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
        private readonly ComponentPositionPlacer $placer,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof HttpOperation && HttpOperation::METHOD_DELETE !== $operation->getMethod()) {
            foreach ($this->positionsWritten($data) as $position) {
                $this->placer->place($position);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
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
