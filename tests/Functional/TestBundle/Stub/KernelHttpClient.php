<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class KernelHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    /** @var list<array{url: string, status: int, headers: array<string, string>}> */
    private static array $requests = [];

    /** @var array<string, int> */
    private static array $statusOverrides = [];

    public function __construct(private readonly HttpKernelInterface $kernel)
    {
        $this->client = new MockHttpClient($this->handle(...));
    }

    public static function reset(): void
    {
        self::$requests = [];
        self::$statusOverrides = [];
    }

    public static function respondWith(string $urlPattern, int $status): void
    {
        self::$statusOverrides[$urlPattern] = $status;
    }

    /**
     * @return list<array{url: string, status: int, headers: array<string, string>}>
     */
    public static function getRequests(): array
    {
        return self::$requests;
    }

    private function handle(string $method, string $url, array $options): MockResponse
    {
        $headers = [];
        $server = [];
        foreach ($options['normalized_headers'] as $name => $values) {
            $headers[$name] = substr($values[0], \strlen($name) + 2);
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $headers[$name];
        }

        foreach (self::$statusOverrides as $pattern => $status) {
            if (preg_match($pattern, $url)) {
                self::$requests[] = ['url' => $url, 'status' => $status, 'headers' => $headers];

                return new MockResponse('', ['http_code' => $status]);
            }
        }

        $response = $this->kernel->handle(Request::create($url, $method, server: $server));
        self::$requests[] = ['url' => $url, 'status' => $response->getStatusCode(), 'headers' => $headers];

        return new MockResponse((string) $response->getContent(), [
            'http_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->allPreserveCaseWithoutCookies(),
        ]);
    }
}
