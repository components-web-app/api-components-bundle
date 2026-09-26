<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class PublishableReadStateProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private PublishableStatusChecker $publishableStatusChecker,
        private PublishableEventListener $publishableEventListener,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        $request = $context['request'] ?? null;
        if (
            !$request instanceof Request
            || !\is_object($data)
            || !$operation instanceof HttpOperation
            || HttpOperation::METHOD_GET !== $operation->getMethod()
            || $operation instanceof CollectionOperationInterface
            || !$this->publishableStatusChecker->getAttributeReader()->isConfigured($data)
        ) {
            return $data;
        }

        return $this->publishableEventListener->mergeDueDraft($request, $data, true);
    }
}
