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

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedFileReportRecord;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class OrphanedFileReportStoreTest extends OrphanedResourceDatabaseTestCase
{
    public function test_nothing_is_fetched_before_a_report_is_saved(): void
    {
        self::assertNull($this->store()->fetch());
    }

    public function test_a_saved_report_is_read_back_from_the_database_with_microseconds(): void
    {
        $this->store()->save(new OrphanedFileReport(
            new \DateTimeImmutable('2026-09-25T10:11:12.345678+00:00'),
            [['adapter' => 'local', 'path' => 'orphan.png']],
            [['resource' => '/dummy_uploadables/1', 'adapter' => 'local', 'path' => 'missing.png']],
        ));
        $this->entityManager->clear();

        $report = $this->store()->fetch();

        self::assertNotNull($report);
        self::assertSame('2026-09-25T10:11:12.345678+00:00', $report->generatedAt->format(OrphanedFileReportRecord::GENERATED_AT_FORMAT));
        self::assertSame([['adapter' => 'local', 'path' => 'orphan.png']], $report->orphanedFiles);
        self::assertSame([['resource' => '/dummy_uploadables/1', 'adapter' => 'local', 'path' => 'missing.png']], $report->missingFiles);
    }

    public function test_the_report_is_one_row_in_its_own_prefixed_table(): void
    {
        $this->store()->save(new OrphanedFileReport(new \DateTimeImmutable('2026-09-25T10:11:12.000001+00:00'), [['adapter' => 'local', 'path' => 'a.png']]));
        $this->store()->save(new OrphanedFileReport(new \DateTimeImmutable('2026-09-25T10:11:13.000002+00:00'), [['adapter' => 'local', 'path' => 'b.png']]));

        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, generated_at, orphaned_files, missing_files FROM _acb_orphaned_file_report');

        self::assertSame(
            [['id' => 1, 'generated_at' => '2026-09-25T10:11:13.000002+00:00', 'orphaned_files' => '[{"adapter":"local","path":"b.png"}]', 'missing_files' => '[]']],
            $rows
        );
        self::assertSame(1, (new OrphanedFileReportRecord())->getId());
    }

    public function test_clearing_removes_the_report(): void
    {
        $store = $this->store();
        $store->save(new OrphanedFileReport(new \DateTimeImmutable()));
        $store->clear();
        $this->entityManager->clear();

        self::assertNull($store->fetch());
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM _acb_orphaned_file_report'));
    }

    public function test_clearing_without_a_report_does_nothing(): void
    {
        $this->store()->clear();

        self::assertNull($this->store()->fetch());
    }

    public function test_a_missing_manager_is_a_logic_error(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(OrphanedFileReportRecord::class);
        (new OrphanedFileReportStore($registry))->fetch();
    }

    private function store(): OrphanedFileReportStore
    {
        return new OrphanedFileReportStore($this->registry);
    }
}
