<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\MessageHandler;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotificationResult;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\InMemoryOrphanedResourceReportStore;

class ScanOrphanedResourcesHandlerTest extends TestCase
{
    private InMemoryOrphanedResourceReportStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryOrphanedResourceReportStore();
    }

    public function test_a_scan_stores_the_report_and_leaves_the_last_notified_report_alone(): void
    {
        $report = $this->report(['/g/1']);

        self::assertSame($report, $this->handler($report)->scan());
        self::assertSame($report, $this->store->fetch());
        self::assertNull($this->store->fetchNotified());
    }

    public function test_the_message_runs_a_scan_that_leaves_the_last_notified_report_alone(): void
    {
        $report = $this->report(['/g/1']);

        ($this->handler($report))(new ScanOrphanedResourcesMessage());

        self::assertSame($report, $this->store->fetch());
        self::assertNull($this->store->fetchNotified());
    }

    public function test_the_comparison_is_against_the_last_notified_report_not_the_stored_one(): void
    {
        $notified = $this->report(['/g/1']);
        $this->store->save($notified);
        $this->store->markNotified($notified);
        $this->store->save($this->report(['/g/1', '/g/2']));
        $report = $this->report(['/g/1', '/g/2']);

        $change = $this->handler($report)->scanAndCompare();

        self::assertSame($report, $change->report);
        self::assertSame($notified, $change->baseline);
        self::assertTrue($change->hasChanged());
        self::assertSame(['componentGroups' => ['/g/2'], 'componentPositions' => [], 'components' => []], $change->getAdded());
        self::assertSame($report, $this->store->fetch());
    }

    public function test_without_a_last_notified_report_there_is_no_baseline_even_when_a_report_is_stored(): void
    {
        $this->store->save($this->report(['/g/1']));

        $change = $this->handler($this->report(['/g/1']))->scanAndCompare();

        self::assertNull($change->baseline);
        self::assertTrue($change->hasChanged());
    }

    public function test_comparing_leaves_the_last_notified_report_alone(): void
    {
        $this->handler($this->report(['/g/1']))->scanAndCompare();

        self::assertNull($this->store->fetchNotified());
    }

    public function test_a_sent_notification_advances_the_last_notified_report(): void
    {
        $handler = $this->handler($this->report(['/g/1']));
        $change = $handler->scanAndCompare();

        $handler->recordNotification($change, OrphanedResourceNotificationResult::Sent);

        self::assertSame($change->report, $this->store->fetchNotified());
    }

    public function test_finding_nothing_to_send_advances_the_last_notified_report(): void
    {
        $handler = $this->handler($this->report());
        $change = $handler->scanAndCompare();

        $handler->recordNotification($change, OrphanedResourceNotificationResult::Unchanged);

        self::assertSame($change->report, $this->store->fetchNotified());
    }

    public function test_a_failed_notification_leaves_the_last_notified_report_alone(): void
    {
        $notified = $this->report();
        $this->store->markNotified($notified);
        $handler = $this->handler($this->report(['/g/1']));

        $handler->recordNotification($handler->scanAndCompare(), OrphanedResourceNotificationResult::Failed);

        self::assertSame($notified, $this->store->fetchNotified());
    }

    public function test_no_recipients_leaves_the_last_notified_report_alone(): void
    {
        $handler = $this->handler($this->report(['/g/1']));

        $handler->recordNotification($handler->scanAndCompare(), OrphanedResourceNotificationResult::NoRecipients);

        self::assertNull($this->store->fetchNotified());
    }

    private function handler(OrphanedResourceReport $report): ScanOrphanedResourcesHandler
    {
        $detector = $this->createStub(OrphanedResourceDetector::class);
        $detector->method('detect')->willReturn($report);

        return new ScanOrphanedResourcesHandler($detector, $this->store);
    }

    /**
     * @param list<string> $componentGroups
     */
    private function report(array $componentGroups = []): OrphanedResourceReport
    {
        return new OrphanedResourceReport(new \DateTimeImmutable(), $componentGroups);
    }
}
