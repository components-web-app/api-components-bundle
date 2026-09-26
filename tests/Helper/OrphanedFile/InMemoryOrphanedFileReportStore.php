<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile;

use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;

class InMemoryOrphanedFileReportStore extends OrphanedFileReportStore
{
    private ?OrphanedFileReport $report = null;

    public function __construct()
    {
    }

    public function save(OrphanedFileReport $report): void
    {
        $this->report = $report;
    }

    public function fetch(): ?OrphanedFileReport
    {
        return $this->report;
    }

    public function clear(): void
    {
        $this->report = null;
    }
}
