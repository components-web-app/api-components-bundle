<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

trait CacheTagsTrait
{
    /**
     * @return list<string>
     */
    protected static function purgedCacheTags(): array
    {
        $purged = [];
        foreach (static::httpClientTraces() as $trace) {
            $headers = $trace['options']->getValue()['normalized_headers'];
            \assert($headers instanceof Data);
            $normalizedHeaders = $headers->getValue();
            foreach (['xkey', 'surrogate-key'] as $headerName) {
                foreach (isset($normalizedHeaders[$headerName]) ? $normalizedHeaders[$headerName]->getValue() : [] as $header) {
                    $value = preg_replace('/^' . preg_quote($headerName, '/') . ':\s*/i', '', $header->getValue());
                    array_push($purged, ...preg_split('/[,\s]+/', trim($value), -1, \PREG_SPLIT_NO_EMPTY));
                }
            }
        }

        return $purged;
    }

    /**
     * @param list<string> $expected
     */
    protected static function assertOnlyCacheTagsPurged(array $expected): void
    {
        $purged = static::purgedCacheTags();
        $actual = array_values(array_unique($purged));
        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual, \sprintf('Tags that were purged were `%s`', implode('`, `', $purged)));
    }

    protected static function assertCacheTagPurgedTimes(string $tag, int $times): void
    {
        $purged = static::purgedCacheTags();

        self::assertCount($times, array_keys($purged, $tag, true), \sprintf('Tags that were purged were `%s`', implode('`, `', $purged)));
    }

    protected static function assertCacheTagNotPurged(string $tag): void
    {
        self::assertNotContains($tag, static::purgedCacheTags());
    }

    protected static function assertNoCachePurgeRequestSent(): void
    {
        $sent = array_map(static fn (array $trace): string => $trace['url'], array_filter(static::httpClientTraces(), static fn (array $trace): bool => 'PURGE' === $trace['method']));

        self::assertSame([], array_values($sent), 'No cache purge request should have been sent.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function httpClientTraces(): array
    {
        $profile = self::getClient()->getProfile();
        self::assertNotFalse($profile, 'The request was not profiled.');
        $collector = $profile->getCollector('http_client');
        \assert($collector instanceof HttpClientDataCollector);

        $traces = [];
        foreach ($collector->getClients() as $clientInfo) {
            foreach ($clientInfo['traces'] as $trace) {
                $traces[] = $trace;
            }
        }

        return $traces;
    }
}
