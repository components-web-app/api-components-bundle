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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileDeletion;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDeleter;

/**
 * @implements ProcessorInterface<mixed, OrphanedFileDeletion>
 */
class OrphanedFileDeletionStateProcessor implements ProcessorInterface
{
    public function __construct(private readonly OrphanedFileDeleter $deleter)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrphanedFileDeletion
    {
        if (!$data instanceof OrphanedFileDeletion) {
            throw new \InvalidArgumentException(\sprintf('Expected an %s.', OrphanedFileDeletion::class));
        }

        return $this->deleter->delete($data->all ? null : $data->paths);
    }
}
