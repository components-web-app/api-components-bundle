<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource;

use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;

class InMemoryOrphanedResourceReportStore extends OrphanedResourceReportStore
{
    private ?OrphanedResourceReport $report = null;

    public function __construct()
    {
    }

    public function save(OrphanedResourceReport $report): ?OrphanedResourceReport
    {
        $previous = $this->report;
        $this->report = $report;

        return $previous;
    }

    public function fetch(): ?OrphanedResourceReport
    {
        return $this->report;
    }

    public function clear(): void
    {
        $this->report = null;
    }
}
