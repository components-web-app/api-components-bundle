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

    public function test_reset_clears_all_state(): void
    {
        $this->data->recordJwtCookiePresent('jwt_cookie');
        $this->data->recordJwtRefreshIssued();
        $this->data->recordJwtCookieCleared();
        $this->data->recordPathResolution('/foo', '/foo');
        $this->data->recordPageDataFound();
        $this->data->recordMercurePublication('https://example.com/resource');

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
    }
}
