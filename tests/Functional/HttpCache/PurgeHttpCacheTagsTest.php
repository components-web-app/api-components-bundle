<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\HttpCache;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\MockClientCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;

final class PurgeHttpCacheTagsTest extends FunctionalTestCase
{
    use CacheTagsTrait;
    use ConsoleTrait;
    use SiteTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel(['routePrefix' => '/_api']);
    }

    public function test_each_tag_option_is_purged_and_nothing_else_is(): void
    {
        [$tester, $collector] = self::runPurgeCommand(['--tag' => ['/_api/_/routes//about-us', 'cwa-html']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertOnlyCacheTagsPurged(['/_api/_/routes//about-us', 'cwa-html'], $collector);
        self::assertStringContainsString('/_api/_/routes//about-us', $tester->getDisplay());
        self::assertStringContainsString('cwa-html', $tester->getDisplay());
    }

    public function test_a_path_purges_the_tag_a_write_to_the_route_at_that_path_purges_with_the_route_import_prefix_when_no_route_exists_there(): void
    {
        [$tester, $collector] = self::runPurgeCommand(['--path' => ['/about-us']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertOnlyCacheTagsPurged(['/_api/_/routes//about-us'], $collector);
        self::assertStringContainsString('/_api/_/routes//about-us', $tester->getDisplay());
    }

    public function test_a_path_purges_the_same_tag_when_a_route_exists_there_and_a_nested_path_keeps_all_its_segments(): void
    {
        self::createRouteWithPage('/about-us/team');

        [$tester, $collector] = self::runPurgeCommand(['--path' => ['/about-us/team', '/']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertOnlyCacheTagsPurged(['/_api/_/routes//about-us/team', '/_api/_/routes//'], $collector);
    }

    public function test_tags_and_paths_given_together_are_purged_in_one_request_each_tag_once_even_when_a_path_resolves_to_a_given_tag(): void
    {
        [$tester, $collector] = self::runPurgeCommand(['--tag' => ['cwa-html', '/_api/_/routes//about-us', 'cwa-html'], '--path' => ['/about-us', '/contact']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertOnlyCacheTagsPurged(['cwa-html', '/_api/_/routes//about-us', '/_api/_/routes//contact'], $collector);
        self::assertCacheTagPurgedTimes('cwa-html', 1, $collector);
        self::assertCacheTagPurgedTimes('/_api/_/routes//about-us', 1, $collector);
        self::assertCacheTagPurgedTimes('/_api/_/routes//contact', 1, $collector);
        self::assertSame(1, substr_count($tester->getDisplay(), '  cwa-html'), $tester->getDisplay());
    }

    public function test_a_purge_that_fails_is_reported_and_exits_1(): void
    {
        MockClientCallback::setCacheUnreachable(true);

        [$tester] = self::runPurgeCommand(['--path' => ['/about-us']]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Failed to purge the HTTP cache tags', $tester->getDisplay());
        self::assertStringContainsString('Could not resolve host', $tester->getDisplay());
    }

    public function test_a_tag_that_the_purge_header_would_split_is_refused_before_anything_is_purged(): void
    {
        [$tester, $collector] = self::runPurgeCommand(['--tag' => ['/one,/two']]);

        self::assertSame(2, $tester->getStatusCode(), $tester->getDisplay());
        self::assertNoCachePurgeRequestSent($collector);
    }

    /**
     * @param array<string, list<string>> $input
     *
     * @return array{CommandTester, HttpClientDataCollector}
     */
    private static function runPurgeCommand(array $input): array
    {
        $tester = null;
        $collector = self::traceOutsideAnHttpRequest(static function () use ($input, &$tester): void {
            $tester = self::runConsoleCommand('silverback:api-components:purge-http-cache', $input);
        });
        \assert($tester instanceof CommandTester);

        return [$tester, $collector];
    }
}
