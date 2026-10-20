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

use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmResult;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpCacheWarmerTest extends TestCase
{
    private const string NEXT = '/componentGroups/*/componentPositions/*/component';
    private const string ROUTE_PRELOAD = '/page/layout' . self::NEXT . self::NEXT . ',/page' . self::NEXT . self::NEXT . ',/pageData/page/layout' . self::NEXT . self::NEXT . ',/pageData/page' . self::NEXT . self::NEXT;
    private const string PAGE_PRELOAD = '/layout' . self::NEXT . self::NEXT . ',' . self::NEXT . self::NEXT;
    private const string LAYOUT_PRELOAD = self::NEXT . self::NEXT;
    private const string PAGE_DATA_PRELOAD = '/page/layout' . self::NEXT . self::NEXT . ',/page' . self::NEXT . self::NEXT;
    private const string GROUP_PRELOAD = '/componentPositions/*/component' . self::NEXT . self::NEXT;
    private const string POSITION_PRELOAD = '/component' . self::NEXT . self::NEXT;
    private const string COMPONENT_PRELOAD = self::NEXT;

    /** @var list<array{url: string, headers: array<string, string>}> */
    private array $requests = [];

    /** @var array<string, MockResponse|callable(): MockResponse> */
    private array $responses = [];

    private MockClock $clock;

    private float $secondsPerRequest = 0;

    protected function setUp(): void
    {
        $this->requests = [];
        $this->responses = [];
        $this->clock = new MockClock('2026-10-13 12:00:00');
        $this->secondsPerRequest = 0;
    }

    public function test_requests_the_route_its_manifest_and_every_manifest_iri_with_the_module_headers(): void
    {
        $this->route('/conference/programme');
        $this->manifest('/conference/programme', [
            $this->node('/_api/_/routes//conference', [
                $this->node('/_api/_/pages/p1', [
                    $this->node('/_api/_/layouts/l1'),
                    $this->node('/_api/_/component_groups/g1', [
                        $this->node('/_api/_/component_positions/cp1', [
                            $this->node('/_api/component/dummy_components/c1'),
                        ]),
                    ]),
                ]),
            ]),
            $this->node('/_api/_/routes//conference/programme', [
                $this->node('/_api/page_data/conference_data/d1', [
                    $this->node('/_api/_/pages/p1'),
                ]),
            ]),
        ]);

        $this->warm(['/conference/programme'], concurrency: 1);

        self::assertSame([
            $this->expected('/_api/_/routes//conference/programme', '/conference/programme', self::ROUTE_PRELOAD),
            $this->expected('/_api/_/resource_manifest//conference/programme', '/conference/programme', null),
            $this->expected('/_api/_/routes//conference', '/conference', self::ROUTE_PRELOAD),
            $this->expected('/_api/_/pages/p1', '/conference/programme', self::PAGE_PRELOAD),
            $this->expected('/_api/_/layouts/l1', '/conference', self::LAYOUT_PRELOAD),
            $this->expected('/_api/_/component_groups/g1', '/conference', self::GROUP_PRELOAD),
            $this->expected('/_api/_/component_positions/cp1', '/conference', self::POSITION_PRELOAD),
            $this->expected('/_api/component/dummy_components/c1', '/conference', self::COMPONENT_PRELOAD),
            $this->expected('/_api/page_data/conference_data/d1', '/conference/programme', self::PAGE_DATA_PRELOAD),
        ], $this->requests);
    }

    public function test_a_depth_without_a_route_uses_its_page_data_as_the_path_and_a_depth_with_neither_uses_the_route_path(): void
    {
        $this->route('/child');
        $this->manifest('/child', [
            $this->node('/_api/page_data/parent_data/d0', [
                $this->node('/_api/_/component_positions/cp0'),
            ]),
            $this->node('/_api/_/pages/routeless', [
                $this->node('/_api/_/component_positions/cp1'),
            ]),
            $this->node('/_api/_/routes//child'),
        ]);

        $this->warm(['/child'], concurrency: 1);

        self::assertSame([
            $this->expected('/_api/_/routes//child', '/child', self::ROUTE_PRELOAD),
            $this->expected('/_api/_/resource_manifest//child', '/child', null),
            $this->expected('/_api/page_data/parent_data/d0', '/_api/page_data/parent_data/d0', self::PAGE_DATA_PRELOAD),
            $this->expected('/_api/_/component_positions/cp0', '/_api/page_data/parent_data/d0', self::POSITION_PRELOAD),
            $this->expected('/_api/_/pages/routeless', '/child', self::PAGE_PRELOAD),
            $this->expected('/_api/_/component_positions/cp1', '/child', self::POSITION_PRELOAD),
        ], $this->requests);
    }

    public function test_a_redirecting_route_gets_only_its_route_lookup(): void
    {
        $this->responses['/_api/_/routes//old'] = $this->json(['@id' => '/_api/_/routes//old', 'redirectPath' => '/new']);

        $result = $this->warm(['/old']);

        self::assertSame(['http://localhost/_api/_/routes//old'], array_column($this->requests, 'url'));
        self::assertSame(1, $result->countRequests());
        self::assertSame(0, $result->countFailed());
    }

    public function test_a_failed_route_lookup_is_counted_and_its_manifest_is_not_requested(): void
    {
        $this->responses['/_api/_/routes//broken'] = new MockResponse('{}', ['http_code' => 500]);
        $this->route('/fine');
        $this->manifest('/fine', [$this->node('/_api/_/routes//fine')]);

        $result = $this->warm(['/broken', '/fine'], concurrency: 1);

        self::assertSame([
            'http://localhost/_api/_/routes//broken',
            'http://localhost/_api/_/routes//fine',
            'http://localhost/_api/_/resource_manifest//fine',
        ], array_column($this->requests, 'url'));
        self::assertSame(1, $result->countFailed());
        self::assertFalse($result->isStopped());
    }

    public function test_an_error_status_or_a_transport_failure_on_a_resource_is_counted_and_never_thrown(): void
    {
        $this->route('/page');
        $this->manifest('/page', [
            $this->node('/_api/_/routes//page', [
                $this->node('/_api/_/pages/missing'),
                $this->node('/_api/_/pages/unreachable'),
                $this->node('/_api/_/pages/fine'),
            ]),
        ]);
        $this->responses['/_api/_/pages/missing'] = new MockResponse('{}', ['http_code' => 404]);
        $this->responses['/_api/_/pages/unreachable'] = new MockResponse('', ['error' => 'Operation timed out']);

        $result = $this->warm(['/page'], concurrency: 1);

        self::assertSame(5, $result->countRequests());
        self::assertSame(2, $result->countFailed());
        $failed = array_values(array_filter($result->getRequests(), static fn ($request) => $request->isFailed()));
        self::assertSame('http://localhost/_api/_/pages/missing', $failed[0]->url);
        self::assertSame(404, $failed[0]->status);
        self::assertNull($failed[0]->error);
        self::assertSame('http://localhost/_api/_/pages/unreachable', $failed[1]->url);
        self::assertSame(0, $failed[1]->status);
        self::assertStringContainsString('Operation timed out', (string) $failed[1]->error);
    }

    public function test_a_resource_listed_twice_with_the_same_headers_is_requested_once(): void
    {
        $this->route('/a');
        $this->manifest('/a', [$this->node('/_api/_/routes//a', [$this->node('/_api/_/layouts/l1')])]);
        $this->route('/b');
        $this->manifest('/b', [$this->node('/_api/_/routes//b', [$this->node('/_api/_/layouts/l1')])]);

        $this->warm(['/a', '/b'], concurrency: 1);

        $layoutRequests = array_values(array_filter($this->requests, static fn ($request) => str_ends_with($request['url'], '/layouts/l1')));
        self::assertCount(2, $layoutRequests);
        self::assertSame('/a', $layoutRequests[0]['headers']['path']);
        self::assertSame('/b', $layoutRequests[1]['headers']['path']);

        $this->requests = [];
        $this->route('/c');
        $this->manifest('/c', [$this->node('/_api/_/routes//c', [$this->node('/_api/_/layouts/l1'), $this->node('/_api/_/layouts/l1')])]);
        $this->warm(['/c'], concurrency: 1);
        self::assertCount(1, array_filter($this->requests, static fn ($request) => str_ends_with($request['url'], '/layouts/l1')));
    }

    public function test_concurrency_one_warms_one_request_at_a_time_and_concurrency_two_starts_the_second_route_before_the_first_manifest(): void
    {
        foreach (['/a', '/b'] as $path) {
            $this->route($path);
            $this->manifest($path, [$this->node('/_api/_/routes/' . $path)]);
        }

        $this->warm(['/a', '/b'], concurrency: 1);
        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/resource_manifest//a',
            'http://localhost/_api/_/routes//b',
            'http://localhost/_api/_/resource_manifest//b',
        ], array_column($this->requests, 'url'));

        $this->requests = [];
        $this->warm(['/a', '/b'], concurrency: 2);
        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/routes//b',
        ], \array_slice(array_column($this->requests, 'url'), 0, 2));
        self::assertCount(4, $this->requests);
    }

    public function test_it_stops_starting_requests_once_the_maximum_time_has_passed(): void
    {
        $this->secondsPerRequest = 10;
        foreach (['/a', '/b'] as $path) {
            $this->route($path);
            $this->manifest($path, [$this->node('/_api/_/routes/' . $path)]);
        }

        $result = $this->warm(['/a', '/b'], concurrency: 1, maxTime: 20);

        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/resource_manifest//a',
        ], array_column($this->requests, 'url'));
        self::assertTrue($result->isStopped());
        self::assertSame(0, $result->countFailed());
    }

    public function test_without_a_maximum_time_it_never_stops(): void
    {
        $this->secondsPerRequest = 3600;
        $this->route('/a');
        $this->manifest('/a', [$this->node('/_api/_/routes//a')]);

        $result = $this->warm(['/a'], concurrency: 1);

        self::assertCount(2, $this->requests);
        self::assertFalse($result->isStopped());
    }

    public function test_hits_and_misses_are_read_from_the_cache_status_header(): void
    {
        $this->responses['/_api/_/routes//a'] = $this->json(['@id' => '/_api/_/routes//a'], ['Cache-Status' => 'Souin; hit; ttl=100; key=GET-localhost-/_api/_/routes//a']);
        $this->responses['/_api/_/resource_manifest//a'] = $this->json(['resource_iris' => [$this->node('/_api/_/routes//a', [$this->node('/_api/_/pages/p1'), $this->node('/_api/_/pages/p2')])]], ['Cache-Status' => 'Souin; fwd=uri-miss; stored; key=GET-localhost-/_api/_/resource_manifest//a']);
        $this->responses['/_api/_/pages/p1'] = $this->json([], ['Cache-Status' => 'Souin; fwd=uri-miss; detail=UPSTREAM-ERROR-OR-EMPTY-RESPONSE']);

        $result = $this->warm(['/a'], concurrency: 1);

        self::assertSame(4, $result->countRequests());
        self::assertSame(1, $result->countHits());
        self::assertSame(2, $result->countMisses());
    }

    public function test_a_gzip_encoded_route_and_manifest_are_decoded(): void
    {
        $this->responses['/_api/_/routes//a'] = new MockResponse(gzencode(json_encode(['@id' => '/_api/_/routes//a'])), ['response_headers' => ['Content-Type' => 'application/ld+json', 'Content-Encoding' => 'Gzip']]);
        $this->responses['/_api/_/resource_manifest//a'] = new MockResponse(gzencode(json_encode(['resource_iris' => [$this->node('/_api/_/routes//a', [$this->node('/_api/_/pages/p1')])]])), ['response_headers' => ['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip']]);

        $this->warm(['/a'], concurrency: 1);

        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/resource_manifest//a',
            'http://localhost/_api/_/pages/p1',
        ], array_column($this->requests, 'url'));
    }

    public function test_the_base_url_may_end_in_a_slash_and_the_encoding_is_sent_as_given(): void
    {
        $this->responses['/_api/_/routes//'] = $this->json(['@id' => '/_api/_/routes//', 'redirectPath' => '/home']);

        $this->warm(['/'], baseUrl: 'http://php.local:8080/', acceptEncoding: 'br');

        self::assertSame('http://php.local:8080/_api/_/routes//', $this->requests[0]['url']);
        self::assertSame('br', $this->requests[0]['headers']['accept-encoding']);
    }

    public function test_no_encoding_sends_no_accept_encoding_header(): void
    {
        $this->responses['/_api/_/routes//'] = $this->json(['@id' => '/_api/_/routes//', 'redirectPath' => '/home']);

        $this->warm(['/'], acceptEncoding: null);

        self::assertArrayNotHasKey('accept-encoding', $this->requests[0]['headers']);
    }

    public function test_a_response_still_arriving_at_the_maximum_time_is_abandoned(): void
    {
        $this->secondsPerRequest = 20;
        $this->responses['/_api/_/routes//a'] = new MockResponse(['', json_encode(['@id' => '/_api/_/routes//a'])]);

        $result = $this->warm(['/a'], concurrency: 1, maxTime: 15);

        self::assertCount(1, $this->requests);
        self::assertSame(0, $result->countRequests());
        self::assertTrue($result->isStopped());
    }

    public function test_a_response_that_pauses_is_waited_for_without_a_maximum_time(): void
    {
        $this->responses['/_api/_/routes//a'] = new MockResponse(['', json_encode(['@id' => '/_api/_/routes//a', 'redirectPath' => '/b'])]);

        $result = $this->warm(['/a'], concurrency: 1);

        self::assertSame(1, $result->countRequests());
        self::assertSame(0, $result->countFailed());
        self::assertFalse($result->isStopped());
    }

    public function test_manifest_nodes_without_an_iri_are_skipped(): void
    {
        $this->route('/a');
        $this->responses['/_api/_/resource_manifest//a'] = $this->json(['resource_iris' => [
            ['children' => [$this->node('/_api/_/pages/hidden')]],
            'not a node',
            ['iri' => '/_api/_/pages/p1', 'children' => 'not a list'],
            ['iri' => 42, 'children' => []],
        ]]);

        $this->warm(['/a'], concurrency: 1);

        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/resource_manifest//a',
            'http://localhost/_api/_/pages/p1',
        ], array_column($this->requests, 'url'));
    }

    public function test_a_route_that_cannot_be_decoded_is_not_followed_and_counts_as_failed_with_the_reason(): void
    {
        $this->responses['/_api/_/routes//compressed'] = new MockResponse('{}', ['response_headers' => ['Content-Encoding' => 'compress']]);
        $this->responses['/_api/_/routes//text'] = new MockResponse('not json');
        $this->responses['/_api/_/routes//scalar'] = new MockResponse('"a string"');
        $this->route('/manifest');
        $this->responses['/_api/_/resource_manifest//manifest'] = new MockResponse('{"resource_iris": "none"}');

        $result = $this->warm(['/compressed', '/text', '/scalar', '/manifest'], concurrency: 1);

        self::assertSame([
            'http://localhost/_api/_/routes//compressed',
            'http://localhost/_api/_/routes//text',
            'http://localhost/_api/_/routes//scalar',
            'http://localhost/_api/_/routes//manifest',
            'http://localhost/_api/_/resource_manifest//manifest',
        ], array_column($this->requests, 'url'));
        self::assertSame(3, $result->countFailed());
        self::assertSame([
            'The response body could not be decoded from Content-Encoding "compress"',
            'The response body is not a JSON object',
            'The response body is not a JSON object',
            null,
            null,
        ], array_map(static fn ($request) => $request->error, $result->getRequests()));
        self::assertSame([200, 200, 200, 200, 200], array_map(static fn ($request) => $request->status, $result->getRequests()));
    }

    public function test_a_manifest_that_cannot_be_decoded_counts_as_failed(): void
    {
        $this->route('/a');
        $this->responses['/_api/_/resource_manifest//a'] = new MockResponse('not json');

        $result = $this->warm(['/a'], concurrency: 1);

        self::assertSame(['http://localhost/_api/_/routes//a', 'http://localhost/_api/_/resource_manifest//a'], array_column($this->requests, 'url'));
        self::assertSame(1, $result->countFailed());
        self::assertSame('The response body is not a JSON object', $result->getRequests()[1]->error);
    }

    public function test_a_brotli_body_without_the_brotli_extension_counts_as_failed(): void
    {
        $this->responses['/_api/_/routes//a'] = new MockResponse(base64_encode('{}'), ['response_headers' => ['Content-Encoding' => 'br']]);

        $result = $this->warm(['/a'], acceptEncoding: 'br', brotliUncompress: 'missing_brotli_uncompress');

        self::assertSame(['http://localhost/_api/_/routes//a'], array_column($this->requests, 'url'));
        self::assertSame(1, $result->countFailed());
        self::assertSame('The response body could not be decoded from Content-Encoding "br"', $result->getRequests()[0]->error);
    }

    public function test_a_brotli_body_is_decoded_with_the_brotli_function(): void
    {
        $this->responses['/_api/_/routes//a'] = new MockResponse(base64_encode('{}'), ['response_headers' => ['Content-Encoding' => 'BR']]);
        $this->responses['/_api/_/resource_manifest//a'] = new MockResponse(base64_encode(json_encode(['resource_iris' => [$this->node('/_api/_/routes//a', [$this->node('/_api/_/pages/p1')])]])), ['response_headers' => ['Content-Encoding' => 'br']]);

        $result = $this->warm(['/a'], concurrency: 1, acceptEncoding: 'br', brotliUncompress: 'base64_decode');

        self::assertSame([
            'http://localhost/_api/_/routes//a',
            'http://localhost/_api/_/resource_manifest//a',
            'http://localhost/_api/_/pages/p1',
        ], array_column($this->requests, 'url'));
        self::assertSame(0, $result->countFailed());
    }

    public function test_it_can_decode_every_encoding_it_asks_for_unless_brotli_is_asked_for_without_the_extension(): void
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $client = new MockHttpClient();
        $withoutBrotli = new HttpCacheWarmer($client, $iriConverter, $this->clock, 'missing_brotli_uncompress');
        $withBrotli = new HttpCacheWarmer($client, $iriConverter, $this->clock, 'base64_decode');

        foreach ([null, 'gzip', 'GZIP', 'identity', 'gzip, deflate', 'brotli'] as $acceptEncoding) {
            self::assertTrue($withoutBrotli->canDecode($acceptEncoding), (string) $acceptEncoding);
        }
        foreach (['br', 'BR', 'gzip, br', 'gzip,br;q=0.5', ' br '] as $acceptEncoding) {
            self::assertFalse($withoutBrotli->canDecode($acceptEncoding), $acceptEncoding);
            self::assertTrue($withBrotli->canDecode($acceptEncoding), $acceptEncoding);
        }
    }

    public function test_a_cap_on_requests_per_connection_asks_every_nth_request_to_close_its_connection_and_changes_no_other_header(): void
    {
        $this->route('/a');
        $this->manifest('/a', [
            $this->node('/_api/_/routes//a', [
                $this->node('/_api/_/pages/p1'),
                $this->node('/_api/_/layouts/l1'),
                $this->node('/_api/_/component_groups/g1'),
            ]),
        ]);

        $this->warm(['/a'], concurrency: 1, requestsPerConnection: 2);

        self::assertSame([null, 'close', null, 'close', null], array_map(static fn (array $request): ?string => $request['headers']['connection'] ?? null, $this->requests));
        $closing = $this->requests[1];
        unset($closing['headers']['connection']);
        self::assertSame($this->expected('/_api/_/resource_manifest//a', '/a', null), $closing);
    }

    public function test_one_request_per_connection_asks_every_request_to_close_its_connection(): void
    {
        $this->route('/a');
        $this->manifest('/a', [$this->node('/_api/_/routes//a', [$this->node('/_api/_/pages/p1')])]);

        $this->warm(['/a', '/b'], concurrency: 2, requestsPerConnection: 1);

        self::assertCount(5, $this->requests);
        foreach ($this->requests as $request) {
            self::assertSame('close', $request['headers']['connection'] ?? null, $request['url']);
        }
    }

    public function test_the_request_carries_the_timeout_as_its_maximum_duration(): void
    {
        $durations = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$durations): MockResponse {
            $durations[] = $options['max_duration'];

            return new MockResponse(json_encode(['redirectPath' => '/b']));
        });
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_/routes//a');

        (new HttpCacheWarmer($client, $iriConverter, $this->clock))->warm(['/a'], 'http://localhost', 1, null, 7.5, null);

        self::assertSame([7.5], $durations);
    }

    public function test_without_an_import_prefix_the_manifest_and_preloads_still_resolve(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['url' => $url, 'headers' => ['preload' => $options['normalized_headers']['preload'][0] ?? null, 'path' => $options['normalized_headers']['path'][0]]];

            return match (parse_url($url, \PHP_URL_PATH)) {
                '/_/routes//a' => new MockResponse('{}'),
                '/_/resource_manifest//a' => new MockResponse(json_encode(['resource_iris' => [$this->node('/_/routes//a', [$this->node('/_/pages/p1')])]])),
                default => new MockResponse('{}'),
            };
        });
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/_/routes//a');

        (new HttpCacheWarmer($client, $iriConverter, $this->clock))->warm(['/a'], 'http://localhost', 1, null, 30.0, null);

        self::assertSame([
            ['url' => 'http://localhost/_/routes//a', 'headers' => ['preload' => 'preload: ' . self::ROUTE_PRELOAD, 'path' => 'path: /a']],
            ['url' => 'http://localhost/_/resource_manifest//a', 'headers' => ['preload' => null, 'path' => 'path: /a']],
            ['url' => 'http://localhost/_/pages/p1', 'headers' => ['preload' => 'preload: ' . self::PAGE_PRELOAD, 'path' => 'path: /a']],
        ], $this->requests);
    }

    /**
     * @param list<string> $paths
     */
    private function warm(array $paths, string $baseUrl = 'http://localhost', int $concurrency = 2, ?float $maxTime = null, ?string $acceptEncoding = 'gzip', string $brotliUncompress = 'brotli_uncompress', ?int $requestsPerConnection = null): HttpCacheWarmResult
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $headers = [];
            foreach ($options['normalized_headers'] as $name => $values) {
                $headers[$name] = substr($values[0], \strlen($name) + 2);
            }
            unset($headers['user-agent']);
            ksort($headers);
            $this->requests[] = ['url' => $url, 'headers' => $headers];
            if ($this->secondsPerRequest) {
                $this->clock->sleep($this->secondsPerRequest);
            }
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $response = $this->responses[$path] ?? $this->json([]);

            return \is_callable($response) ? $response() : clone $response;
        });

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (Route $route) => '/_api/_/routes/' . $route->getPath());

        return (new HttpCacheWarmer($client, $iriConverter, $this->clock, $brotliUncompress))->warm($paths, $baseUrl, $concurrency, $maxTime, 30.0, $acceptEncoding, $requestsPerConnection);
    }

    private function route(string $path): void
    {
        $this->responses['/_api/_/routes/' . $path] = $this->json(['@id' => '/_api/_/routes/' . $path]);
    }

    /**
     * @param list<array{iri: string, children: list<mixed>}> $depths
     */
    private function manifest(string $path, array $depths): void
    {
        $this->responses['/_api/_/resource_manifest/' . $path] = $this->json(['resource_iris' => $depths]);
    }

    /**
     * @param list<array{iri: string, children: list<mixed>}> $children
     *
     * @return array{iri: string, children: list<mixed>}
     */
    private function node(string $iri, array $children = []): array
    {
        return ['iri' => $iri, 'children' => $children];
    }

    /**
     * @param array<string, string> $headers
     */
    private function json(array $data, array $headers = []): MockResponse
    {
        return new MockResponse(json_encode($data), ['response_headers' => ['Content-Type' => 'application/ld+json; charset=utf-8'] + $headers]);
    }

    /**
     * @return array{url: string, headers: array<string, string>}
     */
    private function expected(string $iri, string $path, ?string $preload): array
    {
        $headers = [
            'accept' => 'application/ld+json,application/json',
            'accept-encoding' => 'gzip',
            'path' => $path,
        ];
        if (null !== $preload) {
            $headers['preload'] = $preload;
        }
        ksort($headers);

        return ['url' => 'http://localhost' . $iri, 'headers' => $headers];
    }
}
