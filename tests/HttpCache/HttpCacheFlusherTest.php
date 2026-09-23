<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache;

use ApiPlatform\HttpCache\SouinPurger;
use ApiPlatform\HttpCache\VarnishXKeyPurger;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

class HttpCacheFlusherTest extends TestCase
{
    private const string SOUIN_URL = 'http://localhost:2019/souin-api/souin';

    private array $requests = [];

    public function test_it_sends_a_purge_to_the_souin_flush_endpoint_of_the_invalidation_url(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [$this->invalidationClient(self::SOUIN_URL)]);

        self::assertTrue($flusher->canFlush());
        $flusher->flush();

        self::assertSame([['PURGE', 'http://localhost:2019/souin-api/souin/flush']], $this->sentRequests());
    }

    public function test_a_trailing_slash_on_the_invalidation_url_does_not_double_the_slash(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [$this->invalidationClient(self::SOUIN_URL . '/')]);

        $flusher->flush();

        self::assertSame([['PURGE', 'http://localhost:2019/souin-api/souin/flush']], $this->sentRequests());
    }

    public function test_it_flushes_every_configured_invalidation_url(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL),
            $this->invalidationClient('http://other-cache:2019/souin-api/souin'),
        ]);

        $flusher->flush();

        self::assertSame([
            ['PURGE', 'http://localhost:2019/souin-api/souin/flush'],
            ['PURGE', 'http://other-cache:2019/souin-api/souin/flush'],
        ], $this->sentRequests());
    }

    public function test_the_flush_carries_the_invalidation_clients_request_options(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL, ['headers' => ['X-Cache-Token' => 'secret']]),
        ]);

        $flusher->flush();

        self::assertCount(1, $this->requests);
        self::assertContains('X-Cache-Token: secret', $this->requests[0]['options']['headers']);
    }

    public function test_a_subclass_of_the_souin_purger_can_flush(): void
    {
        $purger = new class([]) extends SouinPurger {
        };
        $flusher = new HttpCacheFlusher($purger, [$this->invalidationClient(self::SOUIN_URL)]);

        self::assertTrue($flusher->canFlush());
        $flusher->flush();

        self::assertCount(1, $this->requests);
    }

    public function test_it_sends_nothing_and_reports_unsupported_for_a_non_souin_purger(): void
    {
        $flusher = new HttpCacheFlusher(new VarnishXKeyPurger([]), [$this->invalidationClient(self::SOUIN_URL)]);

        self::assertFalse($flusher->canFlush());
        $flusher->flush();

        self::assertSame([], $this->requests);
    }

    public function test_it_sends_nothing_and_reports_unsupported_when_no_purger_is_configured(): void
    {
        $flusher = new HttpCacheFlusher(null, [$this->invalidationClient(self::SOUIN_URL)]);

        self::assertFalse($flusher->canFlush());
        $flusher->flush();

        self::assertSame([], $this->requests);
    }

    public function test_it_reports_unsupported_when_no_invalidation_url_is_known(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), []);

        self::assertFalse($flusher->canFlush());
    }

    public function test_a_200_response_is_a_successful_flush(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL, [], new MockResponse('', ['http_code' => 200])),
        ]);

        $flusher->flush();

        self::assertCount(1, $this->requests);
    }

    public function test_a_redirect_response_is_reported_as_a_failure(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL, ['max_redirects' => 0], new MockResponse('', ['http_code' => 300])),
        ]);

        $this->expectException(HttpCacheFlushFailedException::class);
        $this->expectExceptionMessage('responded with 300');

        $flusher->flush();
    }

    public function test_a_non_2xx_response_is_reported_as_a_failure(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL, [], new MockResponse('', ['http_code' => 404])),
        ]);

        $this->expectException(HttpCacheFlushFailedException::class);
        $this->expectExceptionMessage('http://localhost:2019/souin-api/souin/flush');

        $flusher->flush();
    }

    public function test_an_unreachable_cache_is_reported_as_a_failure(): void
    {
        $flusher = new HttpCacheFlusher(new SouinPurger([]), [
            $this->invalidationClient(self::SOUIN_URL, [], new MockResponse('', ['error' => 'Connection refused'])),
        ]);

        $this->expectException(HttpCacheFlushFailedException::class);
        $this->expectExceptionMessage('Connection refused');

        $flusher->flush();
    }

    /**
     * @return array{url: string, client: ScopingHttpClient}
     */
    private function invalidationClient(string $url, array $requestOptions = [], ?MockResponse $response = null): array
    {
        $mock = new MockHttpClient(function (string $method, string $requestUrl, array $options) use ($response): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $requestUrl, 'options' => $options];

            return $response ?? new MockResponse('', ['http_code' => 204]);
        });

        return ['url' => $url, 'client' => ScopingHttpClient::forBaseUri($mock, $url, $requestOptions)];
    }

    private function sentRequests(): array
    {
        return array_map(static fn (array $request) => [$request['method'], $request['url']], $this->requests);
    }
}
