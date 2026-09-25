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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;

/**
 * @implements ProviderInterface<OrphanedResourceReport>
 */
class OrphanedResourceReportStateProvider implements ProviderInterface
{
    public function __construct(private readonly OrphanedResourceReportStore $store)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrphanedResourceReport
    {
        return $this->store->fetch();
    }
}
