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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceDeletion;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDeleter;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;

class OrphanedResourceDeletionStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrphanedResourceDeleter $deleter,
        private readonly ScanOrphanedResourcesHandler $scanner,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrphanedResourceDeletion
    {
        if (!$data instanceof OrphanedResourceDeletion) {
            throw new \InvalidArgumentException(\sprintf('Expected an %s.', OrphanedResourceDeletion::class));
        }

        $result = $this->deleter->delete($data->all ? null : $data->iris);
        $this->scanner->scan();

        return $result;
    }
}
