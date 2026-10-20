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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\CurlHttpClient;

#[RequiresPhpExtension('curl')]
class HttpCacheWarmerConnectionTest extends TestCase
{
    private const int ROUTES = 12;

    /** @var resource|null */
    private $server;

    private string $log;

    private int $port;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'cwa-warm-connections');
        $server = proc_open([\PHP_BINARY, __DIR__ . '/assets/keep-alive-server.php', $this->log], [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        self::assertIsResource($server);
        $this->server = $server;
        stream_set_timeout($pipes[1], 10);
        $port = trim((string) fgets($pipes[1]));
        self::assertMatchesRegularExpression('/\A\d+\z/', $port);
        $this->port = (int) $port;
    }

    protected function tearDown(): void
    {
        if (\is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        @unlink($this->log);
    }

    public function test_by_default_requests_reuse_a_keep_alive_connection(): void
    {
        $this->warm(concurrency: 1, requestsPerConnection: null);

        self::assertSame([self::ROUTES, 1], $this->requestsAndConnections());
    }

    public function test_one_request_per_connection_opens_a_new_connection_for_every_request(): void
    {
        $this->warm(concurrency: 2, requestsPerConnection: 1);

        self::assertSame([self::ROUTES, self::ROUTES], $this->requestsAndConnections());
    }

    public function test_a_cap_on_requests_per_connection_opens_a_new_connection_after_that_many_requests(): void
    {
        $this->warm(concurrency: 1, requestsPerConnection: 4);

        self::assertSame([self::ROUTES, 3], $this->requestsAndConnections());
    }

    private function warm(int $concurrency, ?int $requestsPerConnection): void
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (Route $route) => '/_/routes/' . $route->getPath());
        $paths = array_map(static fn (int $index): string => '/page-' . $index, range(1, self::ROUTES));

        $result = (new HttpCacheWarmer(new CurlHttpClient(), $iriConverter, new MockClock()))->warm($paths, 'http://127.0.0.1:' . $this->port, $concurrency, null, 10.0, null, $requestsPerConnection);

        self::assertSame(self::ROUTES, $result->countRequests());
        self::assertSame(0, $result->countFailed());
    }

    /**
     * @return array{int, int}
     */
    private function requestsAndConnections(): array
    {
        $connections = file($this->log, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [];

        return [\count($connections), \count(array_unique($connections))];
    }
}
