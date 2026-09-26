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
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedResourceReportRecord;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;

class OrphanedResourceReportStoreTest extends OrphanedResourceDatabaseTestCase
{
    public function test_nothing_is_fetched_before_a_report_is_saved(): void
    {
        self::assertNull($this->store()->fetch());
    }

    public function test_a_saved_report_is_read_back_from_the_database(): void
    {
        $this->store()->save(new OrphanedResourceReport(
            new \DateTimeImmutable('2026-09-25T10:11:12.345678+00:00'),
            ['/_/component_groups/1'],
            ['/_/component_positions/1'],
            ['/component/dummy_components/1'],
        ));
        $this->entityManager->clear();

        $report = $this->store()->fetch();

        self::assertNotNull($report);
        self::assertSame('2026-09-25T10:11:12.345678+00:00', $report->generatedAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(['/_/component_groups/1'], $report->componentGroups);
        self::assertSame(['/_/component_positions/1'], $report->componentPositions);
        self::assertSame(['/component/dummy_components/1'], $report->components);
    }

    public function test_the_report_is_one_row_in_the_prefixed_table(): void
    {
        $this->store()->save(new OrphanedResourceReport(new \DateTimeImmutable('2026-09-25T10:11:12.000001+00:00'), ['/g/1']));
        $this->store()->save(new OrphanedResourceReport(new \DateTimeImmutable('2026-09-25T10:11:13.000002+00:00'), ['/g/2']));

        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT generated_at, component_groups, component_positions, components FROM _acb_orphaned_resource_report');

        self::assertSame(
            [['generated_at' => '2026-09-25T10:11:13.000002+00:00', 'component_groups' => '["\/g\/2"]', 'component_positions' => '[]', 'components' => '[]']],
            $rows
        );
    }

    public function test_saving_returns_the_report_it_replaced(): void
    {
        $store = $this->store();

        self::assertNull($store->save(new OrphanedResourceReport(new \DateTimeImmutable(), ['/g/1'])));
        $this->entityManager->clear();

        $previous = $store->save(new OrphanedResourceReport(new \DateTimeImmutable(), [], ['/p/1']));

        self::assertNotNull($previous);
        self::assertSame(['/g/1'], $previous->componentGroups);
        self::assertSame([], $previous->componentPositions);
        self::assertSame(['/p/1'], $store->fetch()?->componentPositions);
    }

    public function test_a_later_report_replaces_the_earlier_one(): void
    {
        $store = $this->store();
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable(), ['/_/component_groups/1']));
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable()));
        $this->entityManager->clear();

        self::assertSame([], $store->fetch()?->componentGroups);
    }

    public function test_clearing_removes_the_report(): void
    {
        $store = $this->store();
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable()));
        $store->clear();
        $this->entityManager->clear();

        self::assertNull($store->fetch());
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM _acb_orphaned_resource_report'));
    }

    public function test_clearing_without_a_report_does_nothing(): void
    {
        $this->store()->clear();

        self::assertNull($this->store()->fetch());
    }

    public function test_the_record_converts_to_and_from_a_report(): void
    {
        $record = new OrphanedResourceReportRecord();
        $record->update(new OrphanedResourceReport(new \DateTimeImmutable('2026-01-02T03:04:05.600000+01:00'), ['/g'], ['/p'], ['/c']));

        $report = $record->toReport();

        self::assertSame('2026-01-02T03:04:05.600000+01:00', $report->generatedAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(['/g'], $report->componentGroups);
        self::assertSame(['/p'], $report->componentPositions);
        self::assertSame(['/c'], $report->components);
    }

    private function store(): OrphanedResourceReportStore
    {
        return new OrphanedResourceReportStore($this->registry);
    }
}
