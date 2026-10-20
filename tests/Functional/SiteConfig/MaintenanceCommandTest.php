<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\SiteConfig;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\MockClientCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;

final class MaintenanceCommandTest extends FunctionalTestCase
{
    use CacheTagsTrait;
    use ConsoleTrait;
    use JsonTrait;
    use MercureTrait;
    use SiteTrait;

    private const string COMMAND = 'silverback:api-components:maintenance';
    private const string KEY = 'maintenanceModeEnabled';
    private const string IRI = '/_/site_config_parameters/maintenanceModeEnabled';
    private const string COLLECTION_IRI = '/_/site_config_parameters';

    public function test_on_creates_the_row_with_the_value_the_admin_ui_writes(): void
    {
        [$tester] = self::runMaintenance('on');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('true', self::storedValue());
        self::assertStringContainsString('Maintenance mode is now on', $tester->getDisplay());
    }

    public function test_off_updates_an_existing_row_with_the_value_the_admin_ui_writes(): void
    {
        self::createSiteConfigParameter(self::KEY, 'true');

        [$tester] = self::runMaintenance('off');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('false', self::storedValue());
        self::assertStringContainsString('Maintenance mode is now off', $tester->getDisplay());
    }

    public function test_on_purges_the_rendered_html_and_the_site_config_tags(): void
    {
        [$tester, $collector] = self::runMaintenance('on');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertCacheTagPurged('cwa-html', $collector);
        self::assertCacheTagPurged(self::IRI, $collector);
        self::assertCacheTagPurged(self::COLLECTION_IRI, $collector);
        self::assertStringContainsString('Purged the HTTP cache tags', $tester->getDisplay());
    }

    public function test_on_publishes_the_change_to_mercure_privately_as_an_api_write_does(): void
    {
        self::createSiteConfigParameter(self::KEY, 'false');
        HubStub::clearPublished();

        [$tester] = self::runMaintenance('on');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame('true', self::mercureUpdateData(self::mercureUpdateFor(self::IRI, true))['value']);
        self::assertNoPublicMercureUpdateContains(self::KEY);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unchangedStates(): iterable
    {
        yield 'on when already on' => ['on', 'true', 'already on'];
        yield 'on when the row holds the "1" the module also accepts' => ['on', '1', 'already on'];
        yield 'off when already off' => ['off', 'false', 'already off'];
    }

    #[DataProvider('unchangedStates')]
    public function test_an_unchanged_state_changes_nothing_says_so_and_still_purges(string $state, string $stored, string $message): void
    {
        self::createSiteConfigParameter(self::KEY, $stored);
        HubStub::clearPublished();

        [$tester, $collector] = self::runMaintenance($state);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString($message, $tester->getDisplay());
        self::assertSame($stored, self::storedValue());
        self::assertOnlyCacheTagsPurged(['cwa-html', self::IRI, self::COLLECTION_IRI], $collector);
        self::assertCacheTagPurgedTimes('cwa-html', 1, $collector);
        self::assertNoMercureUpdatePublished();
    }

    public function test_off_with_no_row_creates_none_says_already_off_and_still_purges(): void
    {
        [$tester, $collector] = self::runMaintenance('off');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('already off', $tester->getDisplay());
        self::assertNull(self::findParameter());
        self::assertOnlyCacheTagsPurged(['cwa-html', self::IRI, self::COLLECTION_IRI], $collector);
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function statuses(): iterable
    {
        yield 'no row' => [null, 'off'];
        yield 'on' => ['true', 'on'];
        yield 'on stored as "1"' => ['1', 'on'];
        yield 'off' => ['false', 'off'];
        yield 'off stored as "0"' => ['0', 'off'];
    }

    #[DataProvider('statuses')]
    public function test_status_prints_on_or_off_and_neither_writes_nor_purges(?string $stored, string $expected): void
    {
        if (null !== $stored) {
            self::createSiteConfigParameter(self::KEY, $stored);
        }

        [$tester, $collector] = self::runMaintenance('status');

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame($expected, trim($tester->getDisplay()));
        self::assertSame($stored, self::storedValue());
        self::assertNoCachePurgeRequestSent($collector);
    }

    public function test_a_purge_that_fails_is_reported_and_exits_1_after_the_write(): void
    {
        MockClientCallback::setCacheUnreachable(true);

        [$tester] = self::runMaintenance('on');

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Failed to purge the HTTP cache tags', $tester->getDisplay());
        self::assertStringContainsString('Could not resolve host', $tester->getDisplay());
        self::assertSame('true', self::storedValue());
    }

    public function test_an_unchanged_state_whose_purge_fails_exits_1(): void
    {
        self::createSiteConfigParameter(self::KEY, 'true');
        MockClientCallback::setCacheUnreachable(true);

        [$tester] = self::runMaintenance('on');

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Failed to purge the HTTP cache tags', $tester->getDisplay());
    }

    public function test_an_invalid_argument_exits_2_before_touching_anything(): void
    {
        QueryCountingMiddleware::reset();

        [$tester, $collector] = self::runMaintenance('enable');

        self::assertSame(2, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('"enable"', $tester->getDisplay());
        self::assertSame([], QueryCountingMiddleware::queries());
        self::assertNoCachePurgeRequestSent($collector);
    }

    public function test_an_unreachable_database_exits_1_with_the_reason_and_purges_nothing(): void
    {
        $tester = null;
        $collector = self::traceOutsideAnHttpRequest(static function () use (&$tester): void {
            UnreachableDatabaseMiddleware::setUnreachable(true);
            try {
                $tester = self::runConsoleCommand(self::COMMAND, ['state' => 'on']);
            } finally {
                UnreachableDatabaseMiddleware::setUnreachable(false);
            }
        });
        \assert($tester instanceof CommandTester);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('could not be read or written', $tester->getDisplay());
        self::assertStringContainsString(UnreachableDatabaseMiddleware::MESSAGE, $tester->getDisplay());
        self::assertNoCachePurgeRequestSent($collector);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function writesAndReads(): iterable
    {
        yield 'on with no row' => ['on', null];
        yield 'off with a row' => ['off', 'true'];
        yield 'on when already on' => ['on', 'true'];
        yield 'status' => ['status', 'true'];
    }

    #[DataProvider('writesAndReads')]
    public function test_it_touches_only_the_site_config_table(string $state, ?string $stored): void
    {
        if (null !== $stored) {
            self::createSiteConfigParameter(self::KEY, $stored);
        }
        $table = static::getEntityManager()->getClassMetadata(SiteConfigParameter::class)->getTableName();
        QueryCountingMiddleware::reset();

        [$tester] = self::runMaintenance($state);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $tables = [];
        foreach (QueryCountingMiddleware::queries() as $sql) {
            preg_match_all('/\b(?:FROM|INTO|UPDATE|JOIN)\s+"?(\w+)"?/i', $sql, $matches);
            array_push($tables, ...$matches[1]);
        }
        self::assertNotSame([], QueryCountingMiddleware::queries());
        self::assertSame([$table], array_values(array_unique($tables)), implode("\n", QueryCountingMiddleware::queries()));
    }

    public function test_quiet_prints_nothing_but_still_writes_and_purges(): void
    {
        [$tester, $collector] = self::runMaintenance('on', OutputInterface::VERBOSITY_QUIET);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('', $tester->getDisplay());
        self::assertSame('true', self::storedValue());
        self::assertCacheTagPurged('cwa-html', $collector);
    }

    public function test_the_value_written_is_read_by_the_site_config_collection_the_module_loads_anonymously(): void
    {
        self::runMaintenance('on');
        $client = static::createClient();

        $client->request('GET', self::COLLECTION_IRI, ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([['key' => self::KEY, 'value' => 'true']], array_map(
            static fn (array $member): array => ['key' => $member['key'], 'value' => $member['value']],
            self::jsonNode('member'),
        ));
    }

    /**
     * @return array{CommandTester, HttpClientDataCollector}
     */
    private static function runMaintenance(string $state, int $verbosity = OutputInterface::VERBOSITY_NORMAL): array
    {
        $tester = null;
        $collector = self::traceOutsideAnHttpRequest(static function () use ($state, $verbosity, &$tester): void {
            $tester = self::runConsoleCommand(self::COMMAND, ['state' => $state], $verbosity);
        });
        \assert($tester instanceof CommandTester);

        return [$tester, $collector];
    }

    private static function findParameter(): ?SiteConfigParameter
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->find(SiteConfigParameter::class, self::KEY);
    }

    private static function storedValue(): array|string|null
    {
        return self::findParameter()?->getValue();
    }
}
