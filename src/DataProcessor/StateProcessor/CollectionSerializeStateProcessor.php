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
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\EventListener\Api\CollectionApiEventListener;

/**
 * @implements ProcessorInterface<mixed, mixed>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class CollectionSerializeStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private CollectionApiEventListener $collectionApiEventListener,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Collection) {
            $this->collectionApiEventListener->transform($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
