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
    private ?OrphanedResourceReport $notified = null;

    public function __construct()
    {
    }

    public function save(OrphanedResourceReport $report): void
    {
        $this->report = $report;
    }

    public function markNotified(OrphanedResourceReport $report): void
    {
        $this->report ??= $report;
        $this->notified = $report;
    }

    public function fetchNotified(): ?OrphanedResourceReport
    {
        return $this->notified;
    }

    public function fetch(): ?OrphanedResourceReport
    {
        return $this->report;
    }

    public function clear(): void
    {
        $this->report = null;
        $this->notified = null;
    }
}
