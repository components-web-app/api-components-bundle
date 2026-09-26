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

use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;

class ScanOrphanedResourcesHandler
{
    public function __construct(
        private readonly OrphanedResourceDetector $detector,
        private readonly OrphanedResourceReportStore $store,
    ) {
    }

    public function __invoke(ScanOrphanedResourcesMessage $message): void
    {
        $this->scan();
    }

    public function scan(): OrphanedResourceReport
    {
        $report = $this->detector->detect();
        $this->store->save($report);

        return $report;
    }
}
