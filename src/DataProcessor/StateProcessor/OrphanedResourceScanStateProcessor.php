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
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, null>
 */
class OrphanedResourceScanStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ?MessageBusInterface $messageBus,
        private readonly ScanOrphanedResourcesHandler $handler,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $message = new ScanOrphanedResourcesMessage();
        if ($this->messageBus) {
            $this->messageBus->dispatch($message);

            return null;
        }
        ($this->handler)($message);

        return null;
    }
}
