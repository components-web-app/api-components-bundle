<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache\Warm;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Psr\Clock\ClockInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class HttpCacheWarmer
{
    private const string KIND_ROUTE = 'route';
    private const string KIND_MANIFEST = 'manifest';
    private const string KIND_RESOURCE = 'resource';
    private const float POLL_SECONDS = 1.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly IriConverterInterface $iriConverter,
        private readonly ClockInterface $clock,
        private readonly string $brotliUncompress = 'brotli_uncompress',
    ) {
    }

    public function canDecode(?string $acceptEncoding): bool
    {
        if (\is_callable($this->brotliUncompress)) {
            return true;
        }

        foreach (explode(',', strtolower($acceptEncoding ?? '')) as $coding) {
            if ('br' === trim(explode(';', $coding, 2)[0])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $paths
     */
    public function warm(array $paths, string $baseUrl, int $concurrency, ?float $maxTime, float $timeout, ?string $acceptEncoding): HttpCacheWarmResult
    {
        $baseUrl = rtrim($baseUrl, '/');
        $deadline = null === $maxTime ? null : $this->now() + $maxTime;
        $routes = new \SplQueue();
        $followUps = new \SplQueue();
        $queued = [];
        $enqueue = static function (array $item) use ($routes, $followUps, &$queued): void {
            if (isset($queued[$item['iri']][$item['path']])) {
                return;
            }
            $queued[$item['iri']][$item['path']] = $item;
            (self::KIND_ROUTE === $item['kind'] ? $routes : $followUps)->enqueue($item);
        };

        foreach ($paths as $path) {
            $routeIri = $this->iriConverter->getIriFromResource((new Route())->setPath($path), UrlGeneratorInterface::ABS_PATH, null, ['uri_variables' => ['id' => 'id']]);
            $enqueue(['kind' => self::KIND_ROUTE, 'iri' => $routeIri, 'path' => $path, 'apiPrefix' => explode('/_/routes/', $routeIri, 2)[0]]);
        }

        $requests = [];
        $active = [];
        while (!$followUps->isEmpty() || !$routes->isEmpty() || $active) {
            while (\count($active) < $concurrency && (!$followUps->isEmpty() || !$routes->isEmpty())) {
                if ($this->isPast($deadline)) {
                    break 2;
                }
                $item = ($followUps->isEmpty() ? $routes : $followUps)->dequeue();
                $response = $this->httpClient->request('GET', $baseUrl . $item['iri'], [
                    'headers' => $this->headers($item, $acceptEncoding),
                    'max_duration' => $timeout,
                    'user_data' => $item,
                ]);
                $active[spl_object_id($response)] = $response;
            }

            foreach ($this->httpClient->stream($active, self::POLL_SECONDS) as $response => $chunk) {
                try {
                    if ($chunk->isTimeout()) {
                        if ($this->isPast($deadline)) {
                            break;
                        }
                        continue;
                    }
                    if ($chunk->isFirst()) {
                        $response->getHeaders(false);
                    }
                    if (!$chunk->isLast()) {
                        continue;
                    }
                    $requests[] = $this->complete($response, $enqueue);
                } catch (TransportExceptionInterface $exception) {
                    $requests[] = new HttpCacheWarmRequest($this->url($response), 0, $this->duration($response), null, $exception->getMessage());
                }
                unset($active[spl_object_id($response)]);
                break;
            }

            if ($this->isPast($deadline)) {
                break;
            }
        }

        $stopped = !$followUps->isEmpty() || !$routes->isEmpty() || [] !== $active;
        foreach ($active as $response) {
            $response->cancel();
        }

        return new HttpCacheWarmResult($requests, $stopped);
    }

    /**
     * @param callable(array<string, string>): void $enqueue
     */
    private function complete(ResponseInterface $response, callable $enqueue): HttpCacheWarmRequest
    {
        $status = $response->getStatusCode();
        $request = new HttpCacheWarmRequest($this->url($response), $status, $this->duration($response), $this->isHit($response));
        if ($request->isFailed()) {
            return $request;
        }

        $item = $response->getInfo('user_data');
        if (self::KIND_RESOURCE === $item['kind']) {
            return $request;
        }

        try {
            $data = $this->decode($response);
        } catch (\UnexpectedValueException $exception) {
            return new HttpCacheWarmRequest($request->url, $request->status, $request->duration, $request->hit, $exception->getMessage());
        }

        if (self::KIND_ROUTE === $item['kind'] && empty($data['redirectPath'])) {
            $enqueue(['kind' => self::KIND_MANIFEST, 'iri' => ModuleRequestHeaders::manifestIri($item['apiPrefix'], $item['path'])] + $item);
        }

        if (self::KIND_MANIFEST === $item['kind']) {
            foreach ($this->manifestRequests($data['resource_iris'] ?? null, $item['apiPrefix'], $item['path']) as [$iri, $path]) {
                $enqueue(['kind' => self::KIND_RESOURCE, 'iri' => $iri, 'path' => $path] + $item);
            }
        }

        return $request;
    }

    /**
     * @return list<array{string, string}>
     */
    private function manifestRequests(mixed $depths, string $apiPrefix, string $routePath): array
    {
        if (!\is_array($depths)) {
            return [];
        }

        $irisByDepth = array_map($this->flatten(...), $depths);
        $iriDepths = [];
        $depthPaths = [];
        foreach ($irisByDepth as $depth => $iris) {
            foreach ($iris as $iri) {
                $iriDepths[$iri] = $depth;
            }
            $depthPath = $this->depthPath($iris, $apiPrefix);
            if (null !== $depthPath) {
                $depthPaths[$depth] = $depthPath;
            }
        }

        $requests = [];
        foreach (array_merge(...array_values($irisByDepth)) as $iri) {
            $requests[] = [$iri, $depthPaths[$iriDepths[$iri]] ?? $routePath];
        }

        return $requests;
    }

    /**
     * @return list<string>
     */
    private function flatten(mixed $node): array
    {
        if (!\is_array($node) || !\is_string($node['iri'] ?? null)) {
            return [];
        }

        $iris = [$node['iri']];
        foreach (\is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
            array_push($iris, ...$this->flatten($child));
        }

        return $iris;
    }

    /**
     * @param list<string> $iris
     */
    private function depthPath(array $iris, string $apiPrefix): ?string
    {
        $routeIriPrefix = ModuleRequestHeaders::routeIriPrefix($apiPrefix);
        foreach ($iris as $iri) {
            if (str_starts_with($iri, $routeIriPrefix)) {
                return substr($iri, \strlen($routeIriPrefix));
            }
        }
        foreach ($iris as $iri) {
            if (ModuleRequestHeaders::isPageData($iri, $apiPrefix)) {
                return $iri;
            }
        }

        return null;
    }

    /**
     * @param array{iri: string, path: string, apiPrefix: string} $item
     *
     * @return array<string, string>
     */
    private function headers(array $item, ?string $acceptEncoding): array
    {
        $headers = [
            'accept' => ModuleRequestHeaders::ACCEPT,
            'path' => $item['path'],
        ];
        $preload = ModuleRequestHeaders::preload($item['iri'], $item['apiPrefix']);
        if (null !== $preload) {
            $headers['preload'] = $preload;
        }
        if (null !== $acceptEncoding) {
            $headers['accept-encoding'] = $acceptEncoding;
        }

        return $headers;
    }

    /**
     * @throws \UnexpectedValueException
     */
    private function decode(ResponseInterface $response): array
    {
        $body = $response->getContent();
        $encoding = strtolower($response->getHeaders()['content-encoding'][0] ?? '');
        $body = match ($encoding) {
            '' => $body,
            'gzip' => @gzdecode($body),
            'br' => \is_callable($this->brotliUncompress) ? ($this->brotliUncompress)($body) : false,
            default => false,
        };
        if (!\is_string($body)) {
            throw new \UnexpectedValueException(\sprintf('The response body could not be decoded from Content-Encoding "%s"', $encoding));
        }

        try {
            $data = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }
        if (!\is_array($data)) {
            throw new \UnexpectedValueException('The response body is not a JSON object');
        }

        return $data;
    }

    private function isHit(ResponseInterface $response): ?bool
    {
        $cacheStatus = $response->getHeaders(false)['cache-status'][0] ?? '';
        if (preg_match('/;\s*hit\b/', $cacheStatus)) {
            return true;
        }
        if (preg_match('/;\s*fwd=/', $cacheStatus)) {
            return false;
        }

        return null;
    }

    private function url(ResponseInterface $response): string
    {
        return (string) $response->getInfo('url');
    }

    private function duration(ResponseInterface $response): float
    {
        return (float) $response->getInfo('total_time');
    }

    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    private function isPast(?float $deadline): bool
    {
        return null !== $deadline && $this->now() >= $deadline;
    }
}
