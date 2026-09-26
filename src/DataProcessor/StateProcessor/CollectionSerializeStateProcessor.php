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
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;

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
        private CollectionPopulator $collectionPopulator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Collection) {
            $this->collectionPopulator->populate($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
