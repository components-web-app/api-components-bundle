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

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableDraftMerger;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class PublishableWriteStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private PublishableStatusChecker $publishableStatusChecker,
        private PublishableDraftMerger $publishableDraftMerger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $request = $context['request'] ?? null;
        if (
            $request instanceof Request
            && \is_object($data)
            && $operation instanceof HttpOperation
            && !$operation instanceof CollectionOperationInterface
            && !\in_array($operation->getMethod(), [HttpOperation::METHOD_GET, HttpOperation::METHOD_DELETE], true)
            && $this->publishableStatusChecker->getAttributeReader()->isConfigured($data)
        ) {
            $data = $this->publishableDraftMerger->mergeDueDraft($request, $data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
