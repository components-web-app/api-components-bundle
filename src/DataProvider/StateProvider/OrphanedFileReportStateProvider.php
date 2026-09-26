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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;

/**
 * @implements ProviderInterface<OrphanedFileReport>
 */
class OrphanedFileReportStateProvider implements ProviderInterface
{
    public function __construct(private readonly OrphanedFileReportStore $store)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrphanedFileReport
    {
        return $this->store->fetch();
    }
}
