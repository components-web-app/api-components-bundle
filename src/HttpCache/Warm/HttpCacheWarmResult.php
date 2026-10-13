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

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class HttpCacheWarmResult
{
    /**
     * @param list<HttpCacheWarmRequest> $requests
     */
    public function __construct(private array $requests, private bool $stopped)
    {
    }

    /**
     * @return list<HttpCacheWarmRequest>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function countRequests(): int
    {
        return \count($this->requests);
    }

    public function countFailed(): int
    {
        return \count(array_filter($this->requests, static fn (HttpCacheWarmRequest $request) => $request->isFailed()));
    }

    public function countHits(): int
    {
        return \count(array_filter($this->requests, static fn (HttpCacheWarmRequest $request) => true === $request->hit));
    }

    public function countMisses(): int
    {
        return \count(array_filter($this->requests, static fn (HttpCacheWarmRequest $request) => false === $request->hit));
    }

    /**
     * @return list<HttpCacheWarmRequest>
     */
    public function getSlowest(int $count): array
    {
        $requests = $this->requests;
        usort($requests, static fn (HttpCacheWarmRequest $a, HttpCacheWarmRequest $b) => $b->duration <=> $a->duration);

        return \array_slice($requests, 0, $count);
    }
}
