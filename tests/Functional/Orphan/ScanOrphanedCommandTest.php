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

use Psr\Cache\CacheItemPoolInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Symfony\Component\Console\Output\OutputInterface;

final class ScanOrphanedCommandTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use JsonTrait;
    use OrphanedResourcesTrait;

    private const string ISO_8601_MICROSECONDS = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/';

    public function test_the_scan_command_prints_the_counts_per_kind_lists_nothing_without_v_and_deletes_nothing(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        $counts = self::countStructuralResources();

        $tester = self::runConsoleCommand('silverback:api-components:scan-orphaned');

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('Component groups: 2', $output);
        self::assertStringContainsString('Component positions: 4', $output);
        self::assertStringContainsString('Components: 5', $output);
        self::assertStringNotContainsString($iris['unused_component'], $output);
        self::assertSame($counts, self::countStructuralResources());
    }

    public function test_with_v_the_scan_command_lists_exactly_what_the_detector_reports(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        $tester = self::runConsoleCommand('silverback:api-components:scan-orphaned', [], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertOutputListsExactlyUnder($output, 'Component groups', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_GROUPS));
        self::assertOutputListsExactlyUnder($output, 'Component positions', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_POSITIONS));
        self::assertOutputListsExactlyUnder($output, 'Components', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_COMPONENTS));
    }

    public function test_clean_orphaned_is_an_alias_of_the_scan_command_and_deletes_nothing(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        $counts = self::countStructuralResources();

        $tester = self::runConsoleCommand('silverback:api-components:clean-orphaned', [], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(0, $tester->getStatusCode());
        self::assertOutputListsExactlyUnder($tester->getDisplay(), 'Components', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_COMPONENTS));
        self::assertSame($counts, self::countStructuralResources());
    }

    public function test_the_report_stored_by_the_scan_command_is_the_one_the_api_returns(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::runConsoleCommand('silverback:api-components:scan-orphaned');
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertEveryKindOfOrphanListed($iris);
    }

    public function test_the_scan_command_stores_the_same_report_as_the_http_scan(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::runConsoleCommand('silverback:api-components:scan-orphaned');
        $remembered = self::storedOrphanedResourcesReportLists();
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');

        self::assertResponseStatusCodeSame(202);
        self::assertSame($remembered, self::storedOrphanedResourcesReportLists());
    }

    public function test_the_scan_command_stores_the_report_in_the_database(): void
    {
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::runScheduledOrphanScan();

        self::assertEveryKindOfOrphanReportStoredInTheDatabase($iris);
    }

    public function test_the_report_survives_a_restart_of_the_api_with_an_empty_application_cache(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();

        $pool = static::getContainer()->get('cache.app');
        \assert($pool instanceof CacheItemPoolInterface);
        self::assertTrue($pool->clear(), 'The application cache could not be cleared');
        static::rebootKernel();
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertEveryKindOfOrphanListed($iris);
    }

    public function test_the_reports_generation_time_has_sub_second_precision(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::runScheduledOrphanScan();

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('generatedAt', self::ISO_8601_MICROSECONDS);
    }

    /**
     * @param array<string, string> $iris
     */
    private static function assertEveryKindOfOrphanListed(array $iris): void
    {
        self::assertJsonNodeListsExactly('componentGroups', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_GROUPS));
        self::assertJsonNodeListsExactly('componentPositions', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_POSITIONS));
        self::assertJsonNodeListsExactly('components', self::irisNamed($iris, self::EVERY_KIND_ORPHANED_COMPONENTS));
    }

    /**
     * @param list<string> $expected
     */
    private static function assertOutputListsExactlyUnder(string $output, string $heading, array $expected): void
    {
        $actual = [];
        $inSection = false;
        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^\S/', $line)) {
                $inSection = str_starts_with($line, $heading . ':');
                continue;
            }
            if ($inSection && '' !== trim($line)) {
                $actual[] = trim($line);
            }
        }
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual, \sprintf('The command output under "%s" differs. Output: %s', $heading, $output));
    }
}
