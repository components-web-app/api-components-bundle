<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\WarmHttpCacheCommand;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WarmHttpCacheCommandTest extends TestCase
{
    use PublishableDatabaseTrait;

    private const string SERVICE_ID = 'silverback.api_components.command.warm_http_cache';

    /** @var list<array{url: string, headers: array<string, list<string>>}> */
    private array $requests = [];

    /** @var array<string, MockResponse> */
    private array $responses = [];

    private MockClock $clock;

    private float $secondsPerRequest = 0;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->clock = new MockClock('2026-10-13 12:00:00');
    }

    public function test_the_command_and_its_services_are_registered(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_http_cache_purger.php');

        $command = $container->getDefinition(self::SERVICE_ID);
        self::assertSame(WarmHttpCacheCommand::class, $command->getClass());
        self::assertTrue($command->hasTag('console.command'));
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(WarmHttpCacheCommand::class));
        self::assertSame(['silverback.api_components.http_cache.public_route_paths', 'silverback.api_components.http_cache.warmer'], array_map('strval', $command->getArguments()));

        $warmer = $container->getDefinition('silverback.api_components.http_cache.warmer');
        self::assertSame(HttpCacheWarmer::class, $warmer->getClass());
        self::assertSame(['http_client', 'api_platform.iri_converter', 'clock'], array_map('strval', $warmer->getArguments()));
        self::assertSame('silverback.api_components.http_cache.warmer', (string) $container->getAlias(HttpCacheWarmer::class));

        $paths = $container->getDefinition('silverback.api_components.http_cache.public_route_paths');
        self::assertSame(PublicRoutePaths::class, $paths->getClass());
        self::assertSame(['doctrine', 'silverback.api_components.doctrine.orm.extension.route'], array_map('strval', $paths->getArguments()));
        self::assertSame('silverback.api_components.http_cache.public_route_paths', (string) $container->getAlias(PublicRoutePaths::class));
    }

    public function test_the_command_is_named(): void
    {
        self::assertSame('silverback:api-components:warm-http-cache', $this->command()->getName());
    }

    public function test_it_warms_every_public_route_and_prints_a_summary(): void
    {
        $this->persistRoute('/about');
        $this->persistRoute('/');
        $this->persistRoute('/draft', null);
        $this->respondWithPage('/');
        $this->respondWithPage('/about');
        $this->responses['/_api/_/pages/about'] = new MockResponse('{}', ['http_code' => 500]);

        $tester = new CommandTester($this->command());
        $status = $tester->execute(['--concurrency' => '1']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([
            'http://localhost/_api/_/routes//',
            'http://localhost/_api/_/resource_manifest//',
            'http://localhost/_api/_/pages/home',
            'http://localhost/_api/_/routes//about',
            'http://localhost/_api/_/resource_manifest//about',
            'http://localhost/_api/_/pages/about',
        ], array_column($this->requests, 'url'));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Warmed 2 routes with 6 requests: 2 hits, 3 misses, 1 failed', $display);
        self::assertMatchesRegularExpression('{Slowest:\n(  \d+\.\d{3}s  \d{3}  http://localhost/\S+\n){5}\z}', $display);
        self::assertStringNotContainsString('Stopped', $display);
        self::assertStringNotContainsString('Failed:', $display);
    }

    public function test_verbose_output_lists_the_failed_requests(): void
    {
        $this->persistRoute('/');
        $this->respondWithPage('/');
        $this->responses['/_api/_/pages/home'] = new MockResponse('{}', ['http_code' => 404]);

        $tester = new CommandTester($this->command());
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertStringContainsString("Failed:\n  404  http://localhost/_api/_/pages/home\n", $tester->getDisplay());
    }

    public function test_verbose_output_gives_a_transport_failure_its_error_and_lists_nothing_when_nothing_failed(): void
    {
        $this->persistRoute('/');
        $this->respondWithPage('/');
        $this->responses['/_api/_/pages/home'] = new MockResponse('', ['error' => 'Operation timed out']);

        $tester = new CommandTester($this->command());
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString("Failed:\n  0  http://localhost/_api/_/pages/home  Operation timed out", $tester->getDisplay());

        unset($this->responses['/_api/_/pages/home']);
        $tester = new CommandTester($this->command());
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertStringContainsString('Warmed 1 route with 3 requests', $tester->getDisplay());
        self::assertStringNotContainsString('Failed:', $tester->getDisplay());
    }

    public function test_it_says_so_when_there_is_no_public_route(): void
    {
        $this->persistRoute('/draft', null);

        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame([], $this->requests);
        self::assertStringContainsString('There is no public route to warm', $tester->getDisplay());
        self::assertStringNotContainsString('Warmed', $tester->getDisplay());
    }

    public function test_paths_replace_the_public_routes_and_a_limit_keeps_the_shortest(): void
    {
        $this->persistRoute('/');
        $this->persistRoute('/a');
        $this->persistRoute('/ab');
        foreach (['/', '/a', '/ab', '/other'] as $path) {
            $this->responses['/_api/_/routes/' . $path] = $this->json(['redirectPath' => '/x']);
        }

        (new CommandTester($this->command()))->execute(['--path' => ['/other']]);
        self::assertSame(['http://localhost/_api/_/routes//other'], array_column($this->requests, 'url'));

        $this->requests = [];
        (new CommandTester($this->command()))->execute(['--limit' => '2']);
        self::assertSame(['http://localhost/_api/_/routes//', 'http://localhost/_api/_/routes//a'], array_column($this->requests, 'url'));

        $this->requests = [];
        (new CommandTester($this->command()))->execute(['--path' => ['/other', '/other', '/a', '/ab'], '--limit' => '2', '--concurrency' => '1']);
        self::assertSame(['http://localhost/_api/_/routes//other', 'http://localhost/_api/_/routes//a'], array_column($this->requests, 'url'));
    }

    public function test_the_base_url_concurrency_and_encoding_options_reach_the_requests(): void
    {
        $this->persistRoute('/a');
        $this->persistRoute('/b');
        foreach (['/a', '/b'] as $path) {
            $this->respondWithPage($path);
        }

        (new CommandTester($this->command()))->execute(['--base-url' => 'http://php.local:8080', '--concurrency' => '1', '--accept-encoding' => 'br']);

        self::assertSame('http://php.local:8080/_api/_/routes//a', $this->requests[0]['url']);
        self::assertSame('http://php.local:8080/_api/_/resource_manifest//a', $this->requests[1]['url']);
        self::assertSame(['accept-encoding: br'], $this->requests[0]['headers']['accept-encoding']);

        $this->requests = [];
        (new CommandTester($this->command()))->execute(['--concurrency' => '2', '--accept-encoding' => 'NONE']);
        self::assertSame('http://localhost/_api/_/routes//b', $this->requests[1]['url']);
        self::assertArrayNotHasKey('accept-encoding', $this->requests[0]['headers']);
    }

    public function test_the_default_encoding_is_gzip_and_the_default_concurrency_is_two(): void
    {
        $this->persistRoute('/a');
        $this->persistRoute('/b');
        $this->persistRoute('/c');
        foreach (['/a', '/b', '/c'] as $path) {
            $this->respondWithPage($path);
        }

        (new CommandTester($this->command()))->execute([]);

        self::assertSame(['accept-encoding: gzip'], $this->requests[0]['headers']['accept-encoding']);
        self::assertSame('http://localhost/_api/_/routes//b', $this->requests[1]['url']);
        self::assertSame('http://localhost/_api/_/resource_manifest//a', $this->requests[2]['url']);
    }

    public function test_it_stops_after_the_maximum_time_and_still_succeeds(): void
    {
        $this->secondsPerRequest = 10;
        $this->persistRoute('/a');
        $this->persistRoute('/b');
        foreach (['/a', '/b'] as $path) {
            $this->respondWithPage($path);
        }

        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute(['--max-time' => '15', '--concurrency' => '1']));
        self::assertCount(2, $this->requests);
        self::assertStringContainsString('Stopped after the maximum time of 15 seconds, before every route was warmed', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidOptions')]
    public function test_invalid_options_are_refused_before_anything_is_requested(array $input, string $message): void
    {
        $this->persistRoute('/');

        $tester = new CommandTester($this->command());

        self::assertSame(Command::INVALID, $tester->execute($input));
        self::assertSame([], $this->requests);
        self::assertStringContainsString($message, $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'relative path' => [['--path' => ['about']], 'A path must start with "/": "about"'];
        yield 'zero concurrency' => [['--concurrency' => '0'], '--concurrency must be a whole number of at least 1'];
        yield 'fractional concurrency' => [['--concurrency' => '1.5'], '--concurrency must be a whole number of at least 1'];
        yield 'zero limit' => [['--limit' => '0'], '--limit must be a whole number of at least 1'];
        yield 'text limit' => [['--limit' => 'all'], '--limit must be a whole number of at least 1'];
        yield 'zero max time' => [['--max-time' => '0'], '--max-time must be a number of seconds greater than 0'];
        yield 'text max time' => [['--max-time' => 'soon'], '--max-time must be a number of seconds greater than 0'];
        yield 'zero timeout' => [['--timeout' => '0'], '--timeout must be a number of seconds greater than 0'];
        yield 'relative base url' => [['--base-url' => 'localhost'], '--base-url must be an http or https URL'];
    }

    private function command(): WarmHttpCacheCommand
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn(false);
        $paths = new PublicRoutePaths($this->registry, new RouteExtension([], $accessChecker, new RouteAncestorGateResolver($this->registry)));

        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['url' => $url, 'headers' => $options['normalized_headers']];
            if ($this->secondsPerRequest) {
                $this->clock->sleep($this->secondsPerRequest);
            }

            return clone ($this->responses[(string) parse_url($url, \PHP_URL_PATH)] ?? $this->json([]));
        });
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (Route $route) => '/_api/_/routes/' . $route->getPath());

        return new WarmHttpCacheCommand($paths, new HttpCacheWarmer($client, $iriConverter, $this->clock));
    }

    private function respondWithPage(string $path): void
    {
        $page = '/_api/_/pages/' . ('/' === $path ? 'home' : trim($path, '/'));
        $this->responses['/_api/_/routes/' . $path] = $this->json([], 'Souin; hit; ttl=10');
        $this->responses['/_api/_/resource_manifest/' . $path] = $this->json(['resource_iris' => [['iri' => '/_api/_/routes/' . $path, 'children' => [['iri' => $page, 'children' => []]]]]], 'Souin; fwd=uri-miss; stored');
        $this->responses[$page] = $this->json([], 'Souin; fwd=uri-miss; stored');
    }

    private function json(array $data, ?string $cacheStatus = null): MockResponse
    {
        $headers = ['Content-Type' => 'application/ld+json'];
        if (null !== $cacheStatus) {
            $headers['Cache-Status'] = $cacheStatus;
        }

        return new MockResponse(json_encode($data), ['response_headers' => $headers]);
    }

    private function persistRoute(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('-1 minute')): void
    {
        $route = (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
        $route->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($route);
        $this->entityManager->flush();
    }
}
