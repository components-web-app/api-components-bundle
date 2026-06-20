<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\DataCollector\CwaDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CwaDataCollectorTest extends TestCase
{
    private CwaCollectorData $collectorData;
    private CwaDataCollector $collector;

    protected function setUp(): void
    {
        $this->collectorData = new CwaCollectorData();
        $this->collector = new CwaDataCollector($this->collectorData);
    }

    public function test_get_name_returns_cwa(): void
    {
        self::assertSame('cwa', $this->collector->getName());
    }

    public function test_collect_with_empty_data(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertFalse($this->collector->isJwtCookiePresent());
        self::assertNull($this->collector->getJwtCookieName());
        self::assertFalse($this->collector->isJwtRefreshIssued());
        self::assertFalse($this->collector->isJwtCookieCleared());
        self::assertNull($this->collector->getResolvedPath());
        self::assertNull($this->collector->getResolvedRouteIri());
        self::assertFalse($this->collector->isPageDataFound());
        self::assertSame([], $this->collector->getPublishedTopics());
        self::assertSame(0, $this->collector->getPublishedTopicsCount());
    }

    public function test_collect_records_jwt_cookie_present(): void
    {
        $this->collectorData->recordJwtCookiePresent('my_jwt_cookie');

        $this->collector->collect(new Request(), new Response());

        self::assertTrue($this->collector->isJwtCookiePresent());
        self::assertSame('my_jwt_cookie', $this->collector->getJwtCookieName());
    }

    public function test_collect_records_jwt_refresh_issued(): void
    {
        $this->collectorData->recordJwtRefreshIssued();

        $this->collector->collect(new Request(), new Response());

        self::assertTrue($this->collector->isJwtRefreshIssued());
    }

    public function test_collect_records_jwt_cookie_cleared(): void
    {
        $this->collectorData->recordJwtCookieCleared();

        $this->collector->collect(new Request(), new Response());

        self::assertTrue($this->collector->isJwtCookieCleared());
    }

    public function test_collect_records_path_resolution(): void
    {
        $this->collectorData->recordPathResolution('/my-page', '/my-page');

        $this->collector->collect(new Request(), new Response());

        self::assertSame('/my-page', $this->collector->getResolvedPath());
        self::assertSame('/my-page', $this->collector->getResolvedRouteIri());
        self::assertFalse($this->collector->isPageDataFound());
    }

    public function test_collect_records_page_data_found(): void
    {
        $this->collectorData->recordPathResolution('/conference', '/conference');
        $this->collectorData->recordPageDataFound();

        $this->collector->collect(new Request(), new Response());

        self::assertTrue($this->collector->isPageDataFound());
    }

    public function test_collect_records_mercure_publications(): void
    {
        $this->collectorData->recordMercurePublication('https://example.com/resource/1');
        $this->collectorData->recordMercurePublication('https://example.com/resource/2');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getPublishedTopicsCount());
        self::assertSame(
            ['https://example.com/resource/1', 'https://example.com/resource/2'],
            $this->collector->getPublishedTopics()
        );
    }

    public function test_reset_clears_collected_data(): void
    {
        $this->collectorData->recordJwtCookiePresent('jwt');
        $this->collectorData->recordJwtRefreshIssued();
        $this->collectorData->recordPathResolution('/foo', '/foo');
        $this->collectorData->recordMercurePublication('https://example.com/resource/1');

        $this->collector->collect(new Request(), new Response());

        // Verify data was collected
        self::assertTrue($this->collector->isJwtCookiePresent());

        // Reset
        $this->collector->reset();

        // After reset, data should be cleared
        self::assertFalse($this->collector->isJwtCookiePresent());
        self::assertNull($this->collector->getJwtCookieName());
        self::assertFalse($this->collector->isJwtRefreshIssued());
        self::assertSame(0, $this->collector->getPublishedTopicsCount());
    }

    public function test_collect_with_exception_does_not_throw(): void
    {
        $exception = new \RuntimeException('Something went wrong');
        $this->collector->collect(new Request(), new Response(), $exception);

        // Should not throw; data is still accessible (empty)
        self::assertFalse($this->collector->isJwtCookiePresent());
    }

    public function test_collect_records_publishable_queries(): void
    {
        $this->collectorData->recordPublishableQuery('App\Entity\Article', 'published-only', 'select');
        $this->collectorData->recordPublishableQuery('App\Entity\Post', 'draft', 'select');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getPublishableQueryCount());
        $queries = $this->collector->getPublishableQueries();
        self::assertCount(2, $queries);
        self::assertSame('App\Entity\Article', $queries[0]['class']);
        self::assertSame('published-only', $queries[0]['mode']);
        self::assertSame('select', $queries[0]['queryType']);
    }

    public function test_collect_empty_publishable_queries(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getPublishableQueryCount());
        self::assertSame([], $this->collector->getPublishableQueries());
    }

    public function test_collect_records_page_data_resolutions(): void
    {
        $this->collectorData->recordPageDataResolution('image', 'App\Entity\Article', null);
        $this->collectorData->recordPageDataResolution('content', null, 'not_found');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getPageDataResolutionCount());
        $resolutions = $this->collector->getPageDataResolutions();
        self::assertCount(2, $resolutions);
        self::assertSame('image', $resolutions[0]['property']);
        self::assertSame('App\Entity\Article', $resolutions[0]['resolvedClass']);
        self::assertNull($resolutions[0]['skipReason']);
        self::assertSame('content', $resolutions[1]['property']);
        self::assertNull($resolutions[1]['resolvedClass']);
        self::assertSame('not_found', $resolutions[1]['skipReason']);
    }

    public function test_collect_empty_page_data_resolutions(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getPageDataResolutionCount());
        self::assertSame([], $this->collector->getPageDataResolutions());
    }

    public function test_collect_records_invalidation_counts(): void
    {
        $this->collectorData->recordInvalidationCount('created');
        $this->collectorData->recordInvalidationCount('created');
        $this->collectorData->recordInvalidationCount('updated');
        $this->collectorData->recordInvalidationCount('deleted');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(4, $this->collector->getTotalInvalidated());
        $counts = $this->collector->getInvalidationCounts();
        self::assertSame(2, $counts['created']);
        self::assertSame(1, $counts['updated']);
        self::assertSame(1, $counts['deleted']);
    }

    public function test_collect_empty_invalidation_counts(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getTotalInvalidated());
        $counts = $this->collector->getInvalidationCounts();
        self::assertSame(0, $counts['created']);
        self::assertSame(0, $counts['updated']);
        self::assertSame(0, $counts['deleted']);
    }

    public function test_collect_records_cache_purged_iris(): void
    {
        $this->collectorData->recordCachePurge(['/_api/_/pages/1', '/_api/_/layouts/1']);
        $this->collectorData->recordCachePurge(['/_api/_/pages/2']);

        $this->collector->collect(new Request(), new Response());

        self::assertSame(3, $this->collector->getCachePurgedCount());
        $iris = $this->collector->getCachePurgedIris();
        self::assertContains('/_api/_/pages/1', $iris);
        self::assertContains('/_api/_/layouts/1', $iris);
        self::assertContains('/_api/_/pages/2', $iris);
    }

    public function test_collect_empty_cache_purged_iris(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getCachePurgedCount());
        self::assertSame([], $this->collector->getCachePurgedIris());
    }

    public function test_collect_records_mercure_private_upgrades(): void
    {
        $this->collectorData->recordMercurePrivateUpgrade(['topic1', 'topic2'], 'App\Entity\Article');
        $this->collectorData->recordMercurePrivateUpgrade(['topic3'], 'App\Entity\Post');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getMercurePrivateUpgradeCount());
        $upgrades = $this->collector->getMercurePrivateUpgrades();
        self::assertCount(2, $upgrades);
        self::assertSame(['topic1', 'topic2'], $upgrades[0]['topics']);
        self::assertSame('App\Entity\Article', $upgrades[0]['resourceClass']);
        self::assertSame(['topic3'], $upgrades[1]['topics']);
    }

    public function test_collect_empty_mercure_private_upgrades(): void
    {
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getMercurePrivateUpgradeCount());
        self::assertSame([], $this->collector->getMercurePrivateUpgrades());
    }

    public function test_reset_also_clears_collector_data(): void
    {
        $this->collectorData->recordPublishableQuery('App\Entity\Article', 'published-only', 'select');
        $this->collectorData->recordPageDataResolution('image', 'App\Entity\Article', null);
        $this->collectorData->recordInvalidationCount('created');
        $this->collectorData->recordCachePurge(['/_api/_/pages/1']);
        $this->collectorData->recordMercurePrivateUpgrade(['topic1'], 'App\Entity\Article');

        $this->collector->collect(new Request(), new Response());
        $this->collector->reset();

        // Re-collect from now-empty collectorData to confirm it was cleared
        $this->collector->collect(new Request(), new Response());

        self::assertSame(0, $this->collector->getPublishableQueryCount());
        self::assertSame(0, $this->collector->getPageDataResolutionCount());
        self::assertSame(0, $this->collector->getTotalInvalidated());
        self::assertSame(0, $this->collector->getCachePurgedCount());
        self::assertSame(0, $this->collector->getMercurePrivateUpgradeCount());
    }

    public function test_is_jwt_cookie_present_returns_false_without_collect(): void
    {
        // Before collect(), data is empty — accessor must return false via ?? false fallback
        self::assertFalse($this->collector->isJwtCookiePresent());
    }

    public function test_is_jwt_refresh_issued_returns_false_without_collect(): void
    {
        self::assertFalse($this->collector->isJwtRefreshIssued());
    }

    public function test_is_jwt_cookie_cleared_returns_false_without_collect(): void
    {
        self::assertFalse($this->collector->isJwtCookieCleared());
    }

    public function test_is_page_data_found_returns_false_without_collect(): void
    {
        self::assertFalse($this->collector->isPageDataFound());
    }

    public function test_published_topics_count_returns_zero_without_collect(): void
    {
        self::assertSame(0, $this->collector->getPublishedTopicsCount());
    }

    public function test_publishable_query_count_returns_zero_without_collect(): void
    {
        // Kills DecrementInteger/IncrementInteger/CastInt mutants on ?? 0 default
        self::assertSame(0, $this->collector->getPublishableQueryCount());
    }

    public function test_page_data_resolution_count_returns_zero_without_collect(): void
    {
        self::assertSame(0, $this->collector->getPageDataResolutionCount());
    }

    public function test_total_invalidated_returns_zero_without_collect(): void
    {
        self::assertSame(0, $this->collector->getTotalInvalidated());
    }

    public function test_cache_purged_count_returns_zero_without_collect(): void
    {
        self::assertSame(0, $this->collector->getCachePurgedCount());
    }

    public function test_mercure_private_upgrade_count_returns_zero_without_collect(): void
    {
        self::assertSame(0, $this->collector->getMercurePrivateUpgradeCount());
    }

    public function test_invalidation_counts_returns_zeros_without_collect(): void
    {
        // Kills ArrayItemRemoval mutant on the default ['created' => 0, 'updated' => 0, 'deleted' => 0]
        $counts = $this->collector->getInvalidationCounts();
        self::assertSame(0, $counts['created']);
        self::assertSame(0, $counts['updated']);
        self::assertSame(0, $counts['deleted']);
    }

    public function test_collect_records_more_than_one_publishable_query(): void
    {
        // Extra precision test: count must be 2, not 1 or -1 — kills Increment/Decrement on non-zero counts
        $this->collectorData->recordPublishableQuery('App\Entity\A', 'published-only', 'select');
        $this->collectorData->recordPublishableQuery('App\Entity\B', 'draft', 'select');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getPublishableQueryCount());
    }

    public function test_collect_records_more_than_one_page_data_resolution(): void
    {
        $this->collectorData->recordPageDataResolution('image', 'App\Entity\Article', null);
        $this->collectorData->recordPageDataResolution('content', null, 'not_found');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getPageDataResolutionCount());
    }

    public function test_collect_total_invalidated_sums_all_categories(): void
    {
        $this->collectorData->recordInvalidationCount('created');
        $this->collectorData->recordInvalidationCount('updated');
        $this->collectorData->recordInvalidationCount('deleted');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(3, $this->collector->getTotalInvalidated());
    }

    public function test_collect_cache_purged_count_reflects_total_iris(): void
    {
        $this->collectorData->recordCachePurge(['/_api/_/pages/1', '/_api/_/pages/2']);

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getCachePurgedCount());
    }

    public function test_collect_mercure_private_upgrade_count_reflects_entries(): void
    {
        $this->collectorData->recordMercurePrivateUpgrade(['topic1'], 'App\Entity\Article');
        $this->collectorData->recordMercurePrivateUpgrade(['topic2'], 'App\Entity\Post');

        $this->collector->collect(new Request(), new Response());

        self::assertSame(2, $this->collector->getMercurePrivateUpgradeCount());
    }
}
