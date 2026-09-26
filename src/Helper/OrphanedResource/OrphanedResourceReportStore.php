<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedResourceReportRecord;

class OrphanedResourceReportStore
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function save(OrphanedResourceReport $report): ?OrphanedResourceReport
    {
        $manager = $this->getManager();
        $record = $manager->find(OrphanedResourceReportRecord::class, OrphanedResourceReportRecord::ID);
        $previous = $record?->toReport();
        if (null === $record) {
            $record = new OrphanedResourceReportRecord();
            $manager->persist($record);
        }
        $record->update($report);
        $manager->flush();

        return $previous;
    }

    public function fetch(): ?OrphanedResourceReport
    {
        return $this->getManager()->find(OrphanedResourceReportRecord::class, OrphanedResourceReportRecord::ID)?->toReport();
    }

    public function clear(): void
    {
        $manager = $this->getManager();
        $record = $manager->find(OrphanedResourceReportRecord::class, OrphanedResourceReportRecord::ID);
        if (null === $record) {
            return;
        }
        $manager->remove($record);
        $manager->flush();
    }

    private function getManager(): ObjectManager
    {
        return $this->registry->getManagerForClass(OrphanedResourceReportRecord::class)
            ?? throw new \LogicException(\sprintf('No Doctrine manager is configured for %s', OrphanedResourceReportRecord::class));
    }
}
