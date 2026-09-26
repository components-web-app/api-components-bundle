<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedFileReportRecord;

class OrphanedFileReportStore
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function save(OrphanedFileReport $report): void
    {
        $manager = $this->getManager();
        $record = $manager->find(OrphanedFileReportRecord::class, OrphanedFileReportRecord::ID);
        if (null === $record) {
            $record = new OrphanedFileReportRecord();
            $manager->persist($record);
        }
        $record->update($report);
        $manager->flush();
    }

    public function fetch(): ?OrphanedFileReport
    {
        return $this->getManager()->find(OrphanedFileReportRecord::class, OrphanedFileReportRecord::ID)?->toReport();
    }

    public function clear(): void
    {
        $manager = $this->getManager();
        $record = $manager->find(OrphanedFileReportRecord::class, OrphanedFileReportRecord::ID);
        if (null === $record) {
            return;
        }
        $manager->remove($record);
        $manager->flush();
    }

    private function getManager(): ObjectManager
    {
        return $this->registry->getManagerForClass(OrphanedFileReportRecord::class)
            ?? throw new \LogicException(\sprintf('No Doctrine manager is configured for %s', OrphanedFileReportRecord::class));
    }
}
