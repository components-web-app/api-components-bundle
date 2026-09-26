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
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportChange;

class OrphanedResourceReportChangeTest extends TestCase
{
    public function test_a_first_report_with_orphans_is_a_change(): void
    {
        self::assertTrue((new OrphanedResourceReportChange($this->report(components: ['/c/1']), null))->hasChanged());
    }

    public function test_a_first_empty_report_is_not_a_change(): void
    {
        self::assertFalse((new OrphanedResourceReportChange($this->report(), null))->hasChanged());
    }

    public function test_the_same_iris_are_not_a_change(): void
    {
        $change = new OrphanedResourceReportChange(
            $this->report(['/g/1'], ['/p/1'], ['/c/1', '/c/2']),
            $this->report(['/g/1'], ['/p/1'], ['/c/1', '/c/2']),
        );

        self::assertFalse($change->hasChanged());
    }

    public function test_the_same_iris_in_another_order_are_not_a_change(): void
    {
        $change = new OrphanedResourceReportChange($this->report(components: ['/c/2', '/c/1']), $this->report(components: ['/c/1', '/c/2']));

        self::assertFalse($change->hasChanged());
    }

    public function test_two_empty_reports_are_not_a_change(): void
    {
        self::assertFalse((new OrphanedResourceReportChange($this->report(), $this->report()))->hasChanged());
    }

    public function test_a_new_orphan_of_any_kind_is_a_change(): void
    {
        self::assertTrue((new OrphanedResourceReportChange($this->report(componentGroups: ['/g/1']), $this->report()))->hasChanged());
        self::assertTrue((new OrphanedResourceReportChange($this->report(componentPositions: ['/p/1']), $this->report()))->hasChanged());
        self::assertTrue((new OrphanedResourceReportChange($this->report(components: ['/c/1']), $this->report()))->hasChanged());
    }

    public function test_an_orphan_no_longer_reported_is_a_change(): void
    {
        self::assertTrue((new OrphanedResourceReportChange($this->report(), $this->report(components: ['/c/1'])))->hasChanged());
    }

    public function test_an_iri_moving_between_kinds_is_a_change(): void
    {
        self::assertTrue((new OrphanedResourceReportChange($this->report(componentGroups: ['/x/1']), $this->report(components: ['/x/1'])))->hasChanged());
    }

    public function test_added_lists_the_iris_new_since_the_previous_report_per_kind(): void
    {
        $change = new OrphanedResourceReportChange(
            $this->report(['/g/1', '/g/2'], ['/p/1'], ['/c/3']),
            $this->report(['/g/1'], ['/p/1'], ['/c/1']),
        );

        self::assertSame(
            ['componentGroups' => ['/g/2'], 'componentPositions' => [], 'components' => ['/c/3']],
            $change->getAdded()
        );
    }

    public function test_every_iri_is_new_when_there_is_no_previous_report(): void
    {
        $change = new OrphanedResourceReportChange($this->report(['/g/1'], ['/p/1'], ['/c/1', '/c/2']), null);

        self::assertSame(
            ['componentGroups' => ['/g/1'], 'componentPositions' => ['/p/1'], 'components' => ['/c/1', '/c/2']],
            $change->getAdded()
        );
    }

    public function test_resolved_counts_the_iris_no_longer_reported(): void
    {
        $change = new OrphanedResourceReportChange(
            $this->report(components: ['/c/3']),
            $this->report(['/g/1'], ['/p/1', '/p/2'], ['/c/1', '/c/3']),
        );

        self::assertSame(4, $change->getResolvedCount());
    }

    public function test_nothing_is_resolved_without_a_previous_report(): void
    {
        self::assertSame(0, (new OrphanedResourceReportChange($this->report(components: ['/c/1']), null))->getResolvedCount());
    }

    public function test_counts_are_the_current_report_per_kind(): void
    {
        $change = new OrphanedResourceReportChange($this->report(['/g/1'], ['/p/1', '/p/2'], []), $this->report(components: ['/c/1']));

        self::assertSame(['componentGroups' => 1, 'componentPositions' => 2, 'components' => 0], $change->getCounts());
    }

    /**
     * @param list<string> $componentGroups
     * @param list<string> $componentPositions
     * @param list<string> $components
     */
    private function report(array $componentGroups = [], array $componentPositions = [], array $components = []): OrphanedResourceReport
    {
        return new OrphanedResourceReport(new \DateTimeImmutable(), $componentGroups, $componentPositions, $components);
    }
}
