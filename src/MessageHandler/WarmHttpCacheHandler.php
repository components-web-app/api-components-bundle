<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\MessageHandler;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmResult;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;

final readonly class WarmHttpCacheHandler
{
    public function __construct(
        private PublicRoutePaths $publicRoutePaths,
        private HttpCacheWarmer $httpCacheWarmer,
        private string $baseUrl = 'http://localhost',
        private int $concurrency = 2,
        private float $maxTime = 60.0,
        private float $timeout = 30.0,
        private ?int $limit = null,
        private ?int $requestsPerConnection = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(WarmHttpCacheMessage $message): ?HttpCacheWarmResult
    {
        if (!$this->httpCacheWarmer->canDecode($message->acceptEncoding)) {
            $this->logger?->error(\sprintf('The HTTP cache was not warmed with Accept-Encoding %s after the flush: the brotli PHP extension is not installed, so the warm could not follow a route to its manifest and components', $message->acceptEncoding));

            return null;
        }

        $paths = $this->publicRoutePaths->find($this->limit);
        if ([] === $paths) {
            $this->logger?->info('There is no public route to warm after the HTTP cache flush');

            return null;
        }

        $result = $this->httpCacheWarmer->warm($paths, $this->baseUrl, $this->concurrency, $this->maxTime, $this->timeout, $message->acceptEncoding, $this->requestsPerConnection);

        $this->logger?->info(\sprintf(
            'Warmed %d %s after the HTTP cache flush with Accept-Encoding %s: %d requests, %d hits, %d misses, %d failed%s',
            \count($paths),
            1 === \count($paths) ? 'route' : 'routes',
            $message->acceptEncoding,
            $result->countRequests(),
            $result->countHits(),
            $result->countMisses(),
            $result->countFailed(),
            $result->isStopped() ? \sprintf('; stopped after the maximum time of %s seconds, before every route was warmed', $this->maxTime) : '',
        ));

        return $result;
    }
}
