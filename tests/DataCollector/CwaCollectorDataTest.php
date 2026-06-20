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

class CwaCollectorDataTest extends TestCase
{
    private CwaCollectorData $data;

    protected function setUp(): void
    {
        $this->data = new CwaCollectorData();
    }

    public function test_default_state_is_empty(): void
    {
        self::assertFalse($this->data->isJwtCookiePresent());
        self::assertNull($this->data->getJwtCookieName());
        self::assertFalse($this->data->isJwtRefreshIssued());
        self::assertFalse($this->data->isJwtCookieCleared());
        self::assertNull($this->data->getResolvedPath());
        self::assertNull($this->data->getResolvedRouteIri());
        self::assertFalse($this->data->isPageDataFound());
        self::assertSame([], $this->data->getPublishedTopics());
        self::assertSame(0, $this->data->getPublishedTopicsCount());
        self::assertSame([], $this->data->getPublishableQueries());
        self::assertSame(0, $this->data->getPublishableQueryCount());
        self::assertSame([], $this->data->getPageDataResolutions());
        self::assertSame(0, $this->data->getPageDataResolutionCount());
        self::assertSame(['created' => 0, 'updated' => 0, 'deleted' => 0], $this->data->getInvalidationCounts());
        self::assertSame(0, $this->data->getTotalInvalidated());
        self::assertSame([], $this->data->getCachePurgedIris());
        self::assertSame(0, $this->data->getCachePurgedCount());
        self::assertSame([], $this->data->getMercurePrivateUpgrades());
        self::assertSame(0, $this->data->getMercurePrivateUpgradeCount());
    }

    public function test_record_jwt_cookie_present(): void
    {
        $this->data->recordJwtCookiePresent('api_components');

        self::assertTrue($this->data->isJwtCookiePresent());
        self::assertSame('api_components', $this->data->getJwtCookieName());
    }

    public function test_record_jwt_refresh_issued(): void
    {
        self::assertFalse($this->data->isJwtRefreshIssued());
        $this->data->recordJwtRefreshIssued();
        self::assertTrue($this->data->isJwtRefreshIssued());
    }

    public function test_record_jwt_cookie_cleared(): void
    {
        self::assertFalse($this->data->isJwtCookieCleared());
        $this->data->recordJwtCookieCleared();
        self::assertTrue($this->data->isJwtCookieCleared());
    }

    public function test_record_path_resolution(): void
    {
        $this->data->recordPathResolution('/blog', '/blog');

        self::assertSame('/blog', $this->data->getResolvedPath());
        self::assertSame('/blog', $this->data->getResolvedRouteIri());
    }

    public function test_record_page_data_found(): void
    {
        self::assertFalse($this->data->isPageDataFound());
        $this->data->recordPageDataFound();
        self::assertTrue($this->data->isPageDataFound());
    }

    public function test_record_mercure_publication_accumulates(): void
    {
        $this->data->recordMercurePublication('https://example.com/a');
        $this->data->recordMercurePublication('https://example.com/b');
        $this->data->recordMercurePublication('https://example.com/c');

        self::assertSame(3, $this->data->getPublishedTopicsCount());
        self::assertSame(
            ['https://example.com/a', 'https://example.com/b', 'https://example.com/c'],
            $this->data->getPublishedTopics()
        );
    }

    public function test_record_publishable_query(): void
    {
        $this->data->recordPublishableQuery('App\Entity\Article', 'draft', 'item');
        $this->data->recordPublishableQuery('App\Entity\Article', 'published_only', 'collection');

        self::assertSame(2, $this->data->getPublishableQueryCount());
        self::assertSame([
            ['class' => 'App\Entity\Article', 'mode' => 'draft', 'queryType' => 'item'],
            ['class' => 'App\Entity\Article', 'mode' => 'published_only', 'queryType' => 'collection'],
        ], $this->data->getPublishableQueries());
    }

    public function test_record_page_data_resolution_success(): void
    {
        $this->data->recordPageDataResolution('heroImage', 'App\Entity\Image', null);

        self::assertSame(1, $this->data->getPageDataResolutionCount());
        self::assertSame([
            ['property' => 'heroImage', 'resolvedClass' => 'App\Entity\Image', 'skipReason' => null],
        ], $this->data->getPageDataResolutions());
    }

    public function test_record_page_data_resolution_skipped(): void
    {
        $this->data->recordPageDataResolution('heroImage', null, 'no_component');

        self::assertSame(1, $this->data->getPageDataResolutionCount());
        self::assertSame([
            ['property' => 'heroImage', 'resolvedClass' => null, 'skipReason' => 'no_component'],
        ], $this->data->getPageDataResolutions());
    }

    public function test_record_invalidation_counts(): void
    {
        $this->data->recordInvalidationCount('created');
        $this->data->recordInvalidationCount('created');
        $this->data->recordInvalidationCount('updated');
        $this->data->recordInvalidationCount('deleted');

        self::assertSame(['created' => 2, 'updated' => 1, 'deleted' => 1], $this->data->getInvalidationCounts());
        self::assertSame(4, $this->data->getTotalInvalidated());
    }

    public function test_record_cache_purge_deduplicates(): void
    {
        $this->data->recordCachePurge(['/_/routes/a', '/_/routes/b']);
        $this->data->recordCachePurge(['/_/routes/b', '/_/routes/c']);

        self::assertSame(3, $this->data->getCachePurgedCount());
        self::assertSame(['/_/routes/a', '/_/routes/b', '/_/routes/c'], $this->data->getCachePurgedIris());
    }

    public function test_record_mercure_private_upgrade(): void
    {
        $this->data->recordMercurePrivateUpgrade(['https://example.com/draft/1'], 'App\Entity\Article');

        self::assertSame(1, $this->data->getMercurePrivateUpgradeCount());
        self::assertSame([
            ['topics' => ['https://example.com/draft/1'], 'resourceClass' => 'App\Entity\Article'],
        ], $this->data->getMercurePrivateUpgrades());
    }

    public function test_reset_clears_all_state(): void
    {
        $this->data->recordJwtCookiePresent('jwt_cookie');
        $this->data->recordJwtRefreshIssued();
        $this->data->recordJwtCookieCleared();
        $this->data->recordPathResolution('/foo', '/foo');
        $this->data->recordPageDataFound();
        $this->data->recordMercurePublication('https://example.com/resource');
        $this->data->recordPublishableQuery('App\Entity\Article', 'draft', 'item');
        $this->data->recordPageDataResolution('heroImage', null, 'no_component');
        $this->data->recordInvalidationCount('created');
        $this->data->recordCachePurge(['/_/routes/a']);
        $this->data->recordMercurePrivateUpgrade(['https://example.com/draft/1'], 'App\Entity\Article');

        $this->data->reset();

        self::assertFalse($this->data->isJwtCookiePresent());
        self::assertNull($this->data->getJwtCookieName());
        self::assertFalse($this->data->isJwtRefreshIssued());
        self::assertFalse($this->data->isJwtCookieCleared());
        self::assertNull($this->data->getResolvedPath());
        self::assertNull($this->data->getResolvedRouteIri());
        self::assertFalse($this->data->isPageDataFound());
        self::assertSame([], $this->data->getPublishedTopics());
        self::assertSame(0, $this->data->getPublishedTopicsCount());
        self::assertSame([], $this->data->getPublishableQueries());
        self::assertSame(0, $this->data->getPublishableQueryCount());
        self::assertSame([], $this->data->getPageDataResolutions());
        self::assertSame(0, $this->data->getPageDataResolutionCount());
        self::assertSame(['created' => 0, 'updated' => 0, 'deleted' => 0], $this->data->getInvalidationCounts());
        self::assertSame(0, $this->data->getTotalInvalidated());
        self::assertSame([], $this->data->getCachePurgedIris());
        self::assertSame(0, $this->data->getCachePurgedCount());
        self::assertSame([], $this->data->getMercurePrivateUpgrades());
        self::assertSame(0, $this->data->getMercurePrivateUpgradeCount());
    }
}
