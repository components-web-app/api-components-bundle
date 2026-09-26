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
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class UserNotificationStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private UserEventListener $userEventListener,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $result = $this->decorated->process($data, $operation, $uriVariables, $context);

        if (
            !$data instanceof AbstractUser
            || !$operation instanceof HttpOperation
            || \in_array($operation->getMethod(), [HttpOperation::METHOD_GET, HttpOperation::METHOD_DELETE], true)
        ) {
            return $result;
        }

        $previousData = HttpOperation::METHOD_POST === $operation->getMethod() ? null : ($context['previous_data'] ?? null);
        $this->userEventListener->postWrite($data, $previousData instanceof AbstractUser ? $previousData : null);

        return $result;
    }
}
