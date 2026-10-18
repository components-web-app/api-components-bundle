<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Orphan;

use Monolog\Level;
use Monolog\LogRecord;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\SwitchableMailer;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\EmailTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

final class OrphanedResourcesNotifyTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use EmailTrait;
    use OrphanedResourcesTrait;

    private const array DEFAULT_RECIPIENTS = ['admin@website.com', 'webmaster@website.com'];

    private const string CHANGED = 'The report has changed: a notification was sent.';

    private const string UNCHANGED = 'The report has not changed: no notification was sent.';

    public function test_a_first_report_with_orphans_is_emailed_to_every_recipient_with_the_counts_the_new_orphans_and_a_link_to_the_admin_page(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::setEmailLinkDefaultOrigin('https://admin.website.com');

        $scan = self::runScheduledOrphanScan();

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertStringContainsString(self::CHANGED, $scan['tester']->getDisplay());
        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationCounts($email, 2, 4, 5);
        self::assertNotificationListsAsNew($email, self::everyKindOfOrphan($iris));
        self::assertNotificationLinksTo($email, 'https://admin.website.com/_cwa/orphaned');
    }

    public function test_a_first_report_with_no_orphans_sends_nothing(): void
    {
        $scan = self::runScheduledOrphanScan();

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertStringContainsString(self::UNCHANGED, $scan['tester']->getDisplay());
        self::assertSame([], $scan['notifications']);
    }

    public function test_a_scan_whose_result_matches_the_last_alert_sends_nothing(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();

        $scan = self::runScheduledOrphanScan();

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertStringContainsString(self::UNCHANGED, $scan['tester']->getDisplay());
        self::assertSame([], $scan['notifications']);
    }

    public function test_a_first_empty_result_sends_nothing_so_the_next_orphan_is_alerted_on_its_own(): void
    {
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, [$groupIri]);
        self::assertNotificationReportsNoLongerOrphaned($email, 0);
    }

    public function test_a_scan_that_finds_a_new_orphan_lists_only_what_is_new_since_the_last_alert(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationCounts($email, 3, 4, 5);
        self::assertNotificationListsAsNew($email, [$groupIri]);
    }

    public function test_a_scheduled_scan_alerts_on_orphans_an_admins_http_scan_found_first(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);

        $scan = self::runScheduledOrphanScan();

        self::assertStringContainsString(self::CHANGED, $scan['tester']->getDisplay());
        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, [$groupIri]);
    }

    public function test_the_first_scheduled_scan_after_an_admins_http_scan_alerts_on_every_orphan(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, self::everyKindOfOrphan($iris));
    }

    public function test_a_scheduled_scan_alerts_on_orphans_the_refresh_after_a_bulk_delete_found_first(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();
        self::requestOrphanDeletion($client, [$iris['unused_component']]);
        self::assertResponseStatusCodeSame(200);

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, [$groupIri]);
        self::assertNotificationReportsNoLongerOrphaned($email, 1);
    }

    public function test_a_result_back_at_the_last_alert_sends_nothing_even_when_an_admins_scan_stored_something_else_in_between(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::requestJsonLd($client, 'DELETE', $groupIri);
        self::assertResponseStatusCodeSame(204);

        $scan = self::runScheduledOrphanScan();

        self::assertStringContainsString(self::UNCHANGED, $scan['tester']->getDisplay());
        self::assertSame([], $scan['notifications']);
    }

    public function test_no_notify_stores_the_report_and_sends_nothing(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        $scan = self::runScheduledOrphanScan('--no-notify');

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertSame([], $scan['notifications']);
        self::assertEveryKindOfOrphanReportStoredInTheDatabase($iris);
    }

    public function test_no_notify_leaves_the_last_alert_alone_so_the_next_scan_alerts_on_the_orphans_it_skipped(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();
        $groupIri = self::createOrphanedComponentGroup();
        self::runScheduledOrphanScan('--no-notify');

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, [$groupIri]);
    }

    public function test_no_notify_on_a_first_scan_leaves_no_last_alert_so_the_next_scan_alerts_on_every_orphan(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan('--no-notify');

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, self::everyKindOfOrphan($iris));
    }

    public function test_with_no_recipients_configured_nothing_is_sent(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::setNotificationRecipients('');

        $scan = self::runScheduledOrphanScan();

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertSame([], $scan['notifications']);
    }

    public function test_with_no_recipients_configured_no_notify_still_scans_and_stores_the_report(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::setNotificationRecipients('');

        $scan = self::runScheduledOrphanScan('--no-notify');

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertEveryKindOfOrphanReportStoredInTheDatabase($iris);
    }

    public function test_a_recipient_in_the_name_address_form_is_emailed_at_that_address(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::setNotificationRecipients('My Website <website@website.com>');

        $scan = self::runScheduledOrphanScan();

        self::assertStringContainsString(self::CHANGED, $scan['tester']->getDisplay());
        self::assertOneNotificationSentTo($scan, ['website@website.com']);
    }

    public function test_without_a_default_origin_the_email_is_sent_without_a_link_and_the_missing_link_is_logged(): void
    {
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        $scan = self::runScheduledOrphanScan();

        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNull($email->getContext()['admin_url'] ?? null, 'The notification links to the admin page');
        self::assertStringNotContainsString('/_cwa/orphaned', (string) $email->getHtmlBody());
        self::assertNotEmpty(
            array_filter($scan['logs'], static fn (LogRecord $record): bool => Level::Warning === $record->level && str_contains($record->message, 'without a link to the admin page')),
            'No missing link was logged for the orphaned resources notification',
        );
    }

    public function test_a_failed_send_keeps_the_new_report_is_logged_and_the_command_still_succeeds(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        SwitchableMailer::setUnreachable(true);

        $scan = self::runScheduledOrphanScan();

        self::assertSame(0, $scan['tester']->getStatusCode());
        self::assertStringContainsString('The report has changed, but the notification could not be sent. The error has been logged.', $scan['tester']->getDisplay());
        self::assertNotEmpty(
            array_filter($scan['logs'], static fn (LogRecord $record): bool => Level::Error === $record->level && ($record->context['exception'] ?? null) instanceof MailerTransportException),
            'No failed orphaned resources notification was logged',
        );
        self::assertEveryKindOfOrphanReportStoredInTheDatabase($iris);
    }

    public function test_a_failed_send_leaves_the_last_alert_alone_so_the_next_scan_emails_the_same_orphans_again(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        SwitchableMailer::setUnreachable(true);
        self::runScheduledOrphanScan();
        SwitchableMailer::setUnreachable(false);

        $scan = self::runScheduledOrphanScan();

        self::assertStringContainsString(self::CHANGED, $scan['tester']->getDisplay());
        $email = self::assertOneNotificationSentTo($scan, self::DEFAULT_RECIPIENTS);
        self::assertNotificationListsAsNew($email, self::everyKindOfOrphan($iris));
    }

    public function test_the_http_scan_never_sends_a_notification(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');

        self::assertResponseStatusCodeSame(202);
        self::assertNoEmailSent();
    }

    public function test_refreshing_the_report_after_a_bulk_delete_never_sends_a_notification(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertNoEmailSent();
    }

    private static function setNotificationRecipients(string $recipients): void
    {
        $_SERVER['ORPHANED_RESOURCES_NOTIFY_RECIPIENTS'] = $_ENV['ORPHANED_RESOURCES_NOTIFY_RECIPIENTS'] = $recipients;
    }

    /**
     * @param array<string, string> $iris
     *
     * @return list<string>
     */
    private static function everyKindOfOrphan(array $iris): array
    {
        return self::irisNamed($iris, [...self::EVERY_KIND_ORPHANED_GROUPS, ...self::EVERY_KIND_ORPHANED_POSITIONS, ...self::EVERY_KIND_ORPHANED_COMPONENTS]);
    }

    /**
     * @param array{notifications: list<TemplatedEmail>} $scan
     * @param list<string>                               $recipients
     */
    private static function assertOneNotificationSentTo(array $scan, array $recipients): TemplatedEmail
    {
        self::assertCount(1, $scan['notifications'], 'Expected one orphaned resources notification');
        $email = $scan['notifications'][0];
        self::assertSame($recipients, array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));

        return $email;
    }

    private static function assertNotificationCounts(TemplatedEmail $email, int $componentGroups, int $componentPositions, int $components): void
    {
        self::assertSame(
            ['componentGroups' => $componentGroups, 'componentPositions' => $componentPositions, 'components' => $components],
            $email->getContext()['counts'] ?? null,
        );
    }

    /**
     * @param list<string> $expected
     */
    private static function assertNotificationListsAsNew(TemplatedEmail $email, array $expected): void
    {
        $actual = array_merge(...array_values($email->getContext()['added'] ?? []));
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }

    private static function assertNotificationReportsNoLongerOrphaned(TemplatedEmail $email, int $count): void
    {
        self::assertSame($count, $email->getContext()['resolved_count'] ?? null);
        $html = (string) $email->getHtmlBody();
        if (0 === $count) {
            self::assertStringNotContainsString('no longer orphaned', $html);

            return;
        }
        self::assertStringContainsString(1 === $count ? '1 resource is no longer orphaned' : \sprintf('%d resources are no longer orphaned', $count), $html);
    }

    private static function assertNotificationLinksTo(TemplatedEmail $email, string $url): void
    {
        self::assertSame($url, $email->getContext()['admin_url'] ?? null);
        self::assertStringContainsString('href="' . $url . '"', (string) $email->getHtmlBody());
    }
}
