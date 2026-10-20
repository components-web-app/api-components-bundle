<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\MessageHandler;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;
use Silverback\ApiComponentsBundle\MessageHandler\WarmHttpCacheHandler;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WarmHttpCacheHandlerTest extends TestCase
{
    use PublishableDatabaseTrait;

    private const string SERVICE_ID = 'silverback.api_components.message_handler.warm_http_cache';

    /** @var list<array{url: string, headers: array<string, list<string>>}> */
    private array $requests = [];

    private MockClock $clock;

    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->clock = new MockClock('2026-10-17 12:00:00');
        $this->logs = new TestHandler();
    }

    public function test_the_handler_is_registered_for_the_message_with_the_warm_services_and_defaults(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_http_cache_purger.php');

        $definition = $container->getDefinition(self::SERVICE_ID);
        self::assertSame(WarmHttpCacheHandler::class, $definition->getClass());
        self::assertSame([['handles' => WarmHttpCacheMessage::class]], $definition->getTag('messenger.message_handler'));
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(WarmHttpCacheHandler::class));
        self::assertSame('silverback.api_components.http_cache.public_route_paths', (string) $definition->getArgument('$publicRoutePaths'));
        self::assertSame('silverback.api_components.http_cache.warmer', (string) $definition->getArgument('$httpCacheWarmer'));
        self::assertSame('logger', (string) $definition->getArgument('$logger'));
    }

    public function test_it_warms_the_public_routes_shortest_first_with_the_messages_encoding_and_logs_a_summary(): void
    {
        $this->persistRoute('/about');
        $this->persistRoute('/');
        $this->persistRoute('/draft', null);

        $result = ($this->handler(brotliUncompress: 'base64_decode'))(new WarmHttpCacheMessage('br'));

        self::assertSame([
            'http://cache.test/_/routes//',
            'http://cache.test/_/resource_manifest//',
            'http://cache.test/_/routes//about',
            'http://cache.test/_/resource_manifest//about',
        ], array_column($this->requests, 'url'));
        foreach ($this->requests as $request) {
            self::assertSame(['accept-encoding: br'], $request['headers']['accept-encoding']);
        }
        self::assertSame(4, $result->countRequests());
        self::assertTrue($this->logs->hasRecordThatContains('Warmed 2 routes after the HTTP cache flush with Accept-Encoding br: 4 requests, 0 hits, 0 misses, 0 failed', Level::Info));
    }

    public function test_the_limit_and_the_concurrency_and_timeout_are_passed_to_the_warm(): void
    {
        $this->persistRoute('/');
        $this->persistRoute('/a');
        $this->persistRoute('/bb');

        ($this->handler(limit: 2, timeout: 7.5))(new WarmHttpCacheMessage('gzip'));

        self::assertSame(['http://cache.test/_/routes//', 'http://cache.test/_/resource_manifest//', 'http://cache.test/_/routes//a', 'http://cache.test/_/resource_manifest//a'], array_column($this->requests, 'url'));
        self::assertSame(7.5, $this->requests[0]['max_duration']);
    }

    public function test_by_default_no_request_asks_to_close_its_connection(): void
    {
        $this->persistRoute('/');

        ($this->handler())(new WarmHttpCacheMessage('gzip'));

        self::assertCount(2, $this->requests);
        foreach ($this->requests as $request) {
            self::assertArrayNotHasKey('connection', $request['headers']);
        }
    }

    public function test_the_requests_per_connection_are_passed_to_the_warm(): void
    {
        $this->persistRoute('/');

        ($this->handler(requestsPerConnection: 2))(new WarmHttpCacheMessage('gzip'));

        self::assertArrayNotHasKey('connection', $this->requests[0]['headers']);
        self::assertSame(['connection: close'], $this->requests[1]['headers']['connection']);
    }

    public function test_it_stops_at_the_maximum_time_and_says_so(): void
    {
        $this->persistRoute('/');
        $this->persistRoute('/a');

        $result = ($this->handler(maxTime: 1.0, secondsPerRequest: 2.0))(new WarmHttpCacheMessage('gzip'));

        self::assertTrue($result->isStopped());
        self::assertCount(1, $this->requests);
        self::assertTrue($this->logs->hasRecordThatContains('stopped after the maximum time of 1 seconds, before every route was warmed', Level::Info));
    }

    public function test_a_brotli_warm_without_the_brotli_extension_requests_nothing_and_logs_an_error(): void
    {
        $this->persistRoute('/');

        self::assertNull(($this->handler(brotliUncompress: 'missing_brotli_uncompress'))(new WarmHttpCacheMessage('br')));
        self::assertSame([], $this->requests);
        self::assertTrue($this->logs->hasRecordThatContains('The HTTP cache was not warmed with Accept-Encoding br after the flush: the brotli PHP extension is not installed, so the warm could not follow a route to its manifest and components', Level::Error));
        self::assertCount(1, $this->logs->getRecords());
    }

    public function test_with_no_public_route_nothing_is_requested(): void
    {
        $this->persistRoute('/draft', null);

        self::assertNull(($this->handler())(new WarmHttpCacheMessage('gzip')));
        self::assertSame([], $this->requests);
        self::assertTrue($this->logs->hasRecordThatContains('There is no public route to warm after the HTTP cache flush', Level::Info));
    }

    private function handler(?int $limit = null, float $maxTime = 60.0, float $timeout = 30.0, float $secondsPerRequest = 0.0, string $brotliUncompress = 'brotli_uncompress', ?int $requestsPerConnection = null): WarmHttpCacheHandler
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn(false);
        $paths = new PublicRoutePaths($this->registry, new RouteExtension([], $accessChecker, new RouteAncestorGateResolver($this->registry)));

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($secondsPerRequest): MockResponse {
            $this->requests[] = ['url' => $url, 'headers' => $options['normalized_headers'], 'max_duration' => $options['max_duration']];
            if ($secondsPerRequest) {
                $this->clock->sleep($secondsPerRequest);
            }

            return new MockResponse('{}', ['response_headers' => ['Content-Type' => 'application/ld+json']]);
        });
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (Route $route) => '/_/routes/' . $route->getPath());

        return new WarmHttpCacheHandler(
            $paths,
            new HttpCacheWarmer($client, $iriConverter, $this->clock, $brotliUncompress),
            'http://cache.test',
            1,
            $maxTime,
            $timeout,
            $limit,
            $requestsPerConnection,
            new Logger('test', [$this->logs]),
        );
    }

    private function persistRoute(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('-1 minute')): void
    {
        $route = (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
        $route->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($route);
        $this->entityManager->flush();
    }
}
