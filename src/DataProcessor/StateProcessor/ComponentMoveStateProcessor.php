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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMover;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveRequestResolver;
use Silverback\ApiComponentsBundle\Model\Component\ComponentMove;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, ComponentMove>
 */
final class ComponentMoveStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ComponentMoveRequestResolver $requestResolver,
        private readonly ComponentMover $mover,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ComponentMove
    {
        $component = $context['read_data'] ?? null;
        if (!$component instanceof AbstractComponent) {
            throw new NotFoundHttpException('Not Found');
        }

        $move = $data instanceof ComponentMove ? $data : new ComponentMove();
        [$from, $to] = $this->requestResolver->resolve($move->from, $move->to);
        $position = $this->mover->move($component, $from, $to);

        return new ComponentMove($move->from, $move->to, $position ? $this->iriConverter->getIriFromResource($position) : null);
    }
}
