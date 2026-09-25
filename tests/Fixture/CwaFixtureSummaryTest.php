<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureSummary;

class CwaFixtureSummaryTest extends TestCase
{
    public function test_an_empty_summary_says_there_was_nothing_to_load(): void
    {
        self::assertSame('CWA scaffold: nothing to load', (string) new CwaFixtureSummary());
    }

    public function test_outcomes_are_listed_created_kept_skipped_with_types_in_a_fixed_order_and_plurals(): void
    {
        $summary = new CwaFixtureSummary();
        $summary->record(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'unidentifiable');
        $summary->record(CwaFixtureSummary::KEPT, CwaFixtureSummary::LAYOUT);
        $summary->record(CwaFixtureSummary::KEPT, CwaFixtureSummary::PAGE);
        $summary->record(CwaFixtureSummary::KEPT, CwaFixtureSummary::PAGE);
        $summary->record(CwaFixtureSummary::CREATED, CwaFixtureSummary::GROUP);
        $summary->record(CwaFixtureSummary::CREATED, CwaFixtureSummary::ROUTE);
        $summary->record(CwaFixtureSummary::CREATED, CwaFixtureSummary::PAGE);
        $summary->record(CwaFixtureSummary::CREATED, CwaFixtureSummary::PAGE);
        $summary->record(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::ENTITY, 'linked to skipped content');
        $summary->record(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'unidentifiable');

        self::assertSame(
            'CWA scaffold: created 2 pages, 1 route, 1 group; kept 2 pages, 1 layout; skipped 2 page data (unidentifiable), 1 entity (linked to skipped content)',
            (string) $summary
        );
    }

    public function test_the_same_type_skipped_for_different_reasons_is_listed_once_per_reason(): void
    {
        $summary = new CwaFixtureSummary();
        $summary->record(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'unidentifiable');
        $summary->record(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'parent skipped');

        self::assertSame('CWA scaffold: skipped 1 page data (parent skipped), 1 page data (unidentifiable)', (string) $summary);
        self::assertSame(2, $summary->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA));
        self::assertSame(1, $summary->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'unidentifiable'));
        self::assertSame(0, $summary->count(CwaFixtureSummary::KEPT, CwaFixtureSummary::PAGE_DATA));
        self::assertSame(0, $summary->count(CwaFixtureSummary::SKIPPED, CwaFixtureSummary::PAGE_DATA, 'other'));
    }
}
