<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\MessageHandler;

use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedFilesMessage;

class ScanOrphanedFilesHandler
{
    public function __construct(
        private readonly OrphanedFileDetector $detector,
        private readonly OrphanedFileReportStore $store,
    ) {
    }

    public function __invoke(ScanOrphanedFilesMessage $message): void
    {
        $this->scan();
    }

    public function scan(): OrphanedFileReport
    {
        $report = $this->detector->detect();
        $this->store->save($report);

        return $report;
    }
}
