<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache\Warm;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmRequest;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmResult;

class HttpCacheWarmResultTest extends TestCase
{
    #[DataProvider('statuses')]
    public function test_only_a2xx_with_no_error_succeeded(int $status, ?string $error, bool $failed): void
    {
        self::assertSame($failed, (new HttpCacheWarmRequest('http://localhost/a', $status, 0.1, null, $error))->isFailed());
    }

    /**
     * @return iterable<string, array{int, ?string, bool}>
     */
    public static function statuses(): iterable
    {
        yield '199' => [199, null, true];
        yield '200' => [200, null, false];
        yield '299' => [299, null, false];
        yield '300' => [300, null, true];
        yield '200 with an error' => [200, 'Operation timed out', true];
    }

    public function test_counts_hits_misses_and_failures(): void
    {
        $result = new HttpCacheWarmResult([
            new HttpCacheWarmRequest('http://localhost/a', 200, 0.1, true),
            new HttpCacheWarmRequest('http://localhost/b', 200, 0.1, true),
            new HttpCacheWarmRequest('http://localhost/c', 200, 0.1, false),
            new HttpCacheWarmRequest('http://localhost/d', 500, 0.1),
        ], false);

        self::assertSame(4, $result->countRequests());
        self::assertSame(2, $result->countHits());
        self::assertSame(1, $result->countMisses());
        self::assertSame(1, $result->countFailed());
        self::assertFalse($result->isStopped());
        self::assertTrue((new HttpCacheWarmResult([], true))->isStopped());
    }

    public function test_the_slowest_come_first_and_are_limited(): void
    {
        $result = new HttpCacheWarmResult([
            new HttpCacheWarmRequest('http://localhost/fast', 200, 0.1),
            new HttpCacheWarmRequest('http://localhost/slowest', 200, 3.0),
            new HttpCacheWarmRequest('http://localhost/slow', 200, 1.0),
        ], false);

        self::assertSame(['http://localhost/slowest', 'http://localhost/slow'], array_map(static fn ($request) => $request->url, $result->getSlowest(2)));
        self::assertSame('http://localhost/fast', $result->getRequests()[0]->url);
    }
}
