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

    public function test_getName_returns_cwa(): void
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
}
