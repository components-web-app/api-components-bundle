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

use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

trait CacheTagsTrait
{
    /**
     * @return list<string>
     */
    protected static function purgedCacheTags(?HttpClientDataCollector $collector = null): array
    {
        $purged = [];
        foreach (self::httpClientTraces($collector) as $trace) {
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
    protected static function assertOnlyCacheTagsPurged(array $expected, ?HttpClientDataCollector $collector = null): void
    {
        $purged = static::purgedCacheTags($collector);
        $actual = array_values(array_unique($purged));
        sort($expected);
        sort($actual);

        self::assertSame($expected, $actual, \sprintf('Tags that were purged were `%s`', implode('`, `', $purged)));
    }

    protected static function assertCacheTagPurged(string $tag, ?HttpClientDataCollector $collector = null): void
    {
        $purged = static::purgedCacheTags($collector);

        self::assertContains($tag, $purged, \sprintf('The cache tag %s was not purged. Tags that were purged were `%s`', $tag, implode('`, `', $purged)));
    }

    protected static function assertCacheTagPurgedTimes(string $tag, int $times, ?HttpClientDataCollector $collector = null): void
    {
        $purged = static::purgedCacheTags($collector);

        self::assertCount($times, array_keys($purged, $tag, true), \sprintf('Tags that were purged were `%s`', implode('`, `', $purged)));
    }

    protected static function assertCacheTagNotPurged(string $tag, ?HttpClientDataCollector $collector = null): void
    {
        self::assertNotContains($tag, static::purgedCacheTags($collector));
    }

    protected static function assertNoCachePurgeRequestSent(?HttpClientDataCollector $collector = null): void
    {
        $sent = array_map(static fn (array $trace): string => $trace['url'], array_filter(self::httpClientTraces($collector), static fn (array $trace): bool => 'PURGE' === $trace['method']));

        self::assertSame([], array_values($sent), 'No cache purge request should have been sent.');
    }

    protected static function manifestCacheTag(string $iri): string
    {
        return CwaTagCollector::MANIFEST_TAG_PREFIX . $iri;
    }

    /**
     * @return list<string>
     */
    protected static function responseCacheTags(): array
    {
        $headers = self::getClient()->getResponse()->headers;
        $tags = [];
        foreach (['xkey', 'surrogate-key'] as $headerName) {
            foreach ($headers->all($headerName) as $value) {
                array_push($tags, ...preg_split('/[,\s]+/', trim((string) $value), -1, \PREG_SPLIT_NO_EMPTY));
            }
        }

        return $tags;
    }

    protected static function assertResponseCarriesNoCacheTags(): void
    {
        self::assertSame([], static::responseCacheTags(), \sprintf('The response carries the cache tags `%s`', implode('`, `', static::responseCacheTags())));
    }

    protected static function assertResponseHasCacheTag(string $tag): void
    {
        self::assertContains($tag, static::responseCacheTags(), \sprintf('The cache tag %s is not in the response. Tags in the response were `%s`', $tag, implode('`, `', static::responseCacheTags())));
    }

    protected static function assertResponseLacksCacheTag(string $tag): void
    {
        self::assertNotContains($tag, static::responseCacheTags(), \sprintf('The cache tag %s is in the response.', $tag));
    }

    /**
     * @param \Closure(ContainerInterface): void $work
     */
    protected static function traceOutsideAnHttpRequest(\Closure $work): HttpClientDataCollector
    {
        $kernel = static::$kernel ?? static::bootKernel();
        $kernel->shutdown();
        $kernel->boot();
        $container = static::getContainer();
        self::assertNull($container->get('request_stack')->getMainRequest(), 'A request is on the request stack, so this would not be outside an HTTP request.');

        $work($container);

        $collector = $container->get('data_collector.http_client');
        \assert($collector instanceof HttpClientDataCollector);
        $collector->lateCollect();

        return $collector;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function httpClientTraces(?HttpClientDataCollector $collector = null): array
    {
        if (null === $collector) {
            $profile = self::getClient()->getProfile();
            self::assertNotFalse($profile, 'The request was not profiled.');
            $collector = $profile->getCollector('http_client');
            \assert($collector instanceof HttpClientDataCollector);
        }

        $traces = [];
        foreach ($collector->getClients() as $clientInfo) {
            foreach ($clientInfo['traces'] as $trace) {
                $traces[] = $trace;
            }
        }

        return $traces;
    }
}
