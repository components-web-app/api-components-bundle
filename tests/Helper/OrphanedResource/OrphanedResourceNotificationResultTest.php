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
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotificationResult;

class OrphanedResourceNotificationResultTest extends TestCase
{
    public function test_a_sent_notification_advances_the_baseline(): void
    {
        self::assertTrue(OrphanedResourceNotificationResult::Sent->advancesBaseline());
    }

    public function test_finding_nothing_to_send_advances_the_baseline(): void
    {
        self::assertTrue(OrphanedResourceNotificationResult::Unchanged->advancesBaseline());
    }

    public function test_a_failed_notification_does_not_advance_the_baseline(): void
    {
        self::assertFalse(OrphanedResourceNotificationResult::Failed->advancesBaseline());
    }

    public function test_no_recipients_does_not_advance_the_baseline(): void
    {
        self::assertFalse(OrphanedResourceNotificationResult::NoRecipients->advancesBaseline());
    }
}
