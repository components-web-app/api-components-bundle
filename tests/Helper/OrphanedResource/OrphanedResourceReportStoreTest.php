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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class OrphanedResourceReportStoreTest extends TestCase
{
    public function test_nothing_is_fetched_before_a_report_is_saved(): void
    {
        self::assertNull((new OrphanedResourceReportStore(new ArrayAdapter()))->fetch());
    }

    public function test_a_saved_report_is_fetched_by_a_new_store_on_the_same_pool(): void
    {
        $pool = new ArrayAdapter();
        (new OrphanedResourceReportStore($pool))->save(new OrphanedResourceReport(
            new \DateTimeImmutable('2026-09-25T10:11:12+00:00'),
            ['/_/component_groups/1'],
            ['/_/component_positions/1'],
            ['/component/dummy_components/1'],
        ));

        $report = (new OrphanedResourceReportStore($pool))->fetch();

        self::assertNotNull($report);
        self::assertSame('2026-09-25T10:11:12+00:00', $report->generatedAt->format(\DateTimeInterface::ATOM));
        self::assertSame(['/_/component_groups/1'], $report->componentGroups);
        self::assertSame(['/_/component_positions/1'], $report->componentPositions);
        self::assertSame(['/component/dummy_components/1'], $report->components);
    }

    public function test_the_report_is_stored_as_plain_data_so_no_class_is_needed_to_read_it(): void
    {
        $pool = new ArrayAdapter();
        (new OrphanedResourceReportStore($pool))->save(new OrphanedResourceReport(new \DateTimeImmutable('2026-09-25T10:11:12+00:00')));

        self::assertSame(
            ['generatedAt' => '2026-09-25T10:11:12+00:00', 'componentGroups' => [], 'componentPositions' => [], 'components' => []],
            $pool->getItem(OrphanedResourceReportStore::CACHE_KEY)->get()
        );
    }

    public function test_a_later_report_replaces_the_earlier_one(): void
    {
        $store = new OrphanedResourceReportStore(new ArrayAdapter());
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable(), ['/_/component_groups/1']));
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable()));

        self::assertSame([], $store->fetch()?->componentGroups);
    }

    public function test_clearing_removes_the_report(): void
    {
        $store = new OrphanedResourceReportStore(new ArrayAdapter());
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable()));
        $store->clear();

        self::assertNull($store->fetch());
    }

    public function test_an_unreadable_cache_entry_is_treated_as_no_report(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem(OrphanedResourceReportStore::CACHE_KEY)->set('not a report'));

        self::assertNull((new OrphanedResourceReportStore($pool))->fetch());
    }
}
