<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataCollector;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Shared in-request store that listeners push profiler data into.
 * The DataCollector reads from this at collect() time.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class CwaCollectorData implements ResetInterface
{
    // --- JWT ---
    private bool $jwtCookiePresent = false;
    private ?string $jwtCookieName = null;
    private bool $jwtRefreshIssued = false;
    private bool $jwtCookieCleared = false;

    // --- Route resolution ---
    private ?string $resolvedPath = null;
    private ?string $resolvedRouteIri = null;
    private bool $pageDataFound = false;

    // --- Mercure ---
    /** @var list<string> */
    private array $publishedTopics = [];

    // -----------------------------------------------------------------------
    // JWT
    // -----------------------------------------------------------------------

    public function recordJwtCookiePresent(string $cookieName): void
    {
        $this->jwtCookiePresent = true;
        $this->jwtCookieName = $cookieName;
    }

    public function recordJwtRefreshIssued(): void
    {
        $this->jwtRefreshIssued = true;
    }

    public function recordJwtCookieCleared(): void
    {
        $this->jwtCookieCleared = true;
    }

    public function isJwtCookiePresent(): bool
    {
        return $this->jwtCookiePresent;
    }

    public function getJwtCookieName(): ?string
    {
        return $this->jwtCookieName;
    }

    public function isJwtRefreshIssued(): bool
    {
        return $this->jwtRefreshIssued;
    }

    public function isJwtCookieCleared(): bool
    {
        return $this->jwtCookieCleared;
    }

    // -----------------------------------------------------------------------
    // Route resolution
    // -----------------------------------------------------------------------

    public function recordPathResolution(string $path, string $routeIri): void
    {
        $this->resolvedPath = $path;
        $this->resolvedRouteIri = $routeIri;
    }

    public function recordPageDataFound(): void
    {
        $this->pageDataFound = true;
    }

    public function getResolvedPath(): ?string
    {
        return $this->resolvedPath;
    }

    public function getResolvedRouteIri(): ?string
    {
        return $this->resolvedRouteIri;
    }

    public function isPageDataFound(): bool
    {
        return $this->pageDataFound;
    }

    // -----------------------------------------------------------------------
    // Mercure
    // -----------------------------------------------------------------------

    public function recordMercurePublication(string $topic): void
    {
        $this->publishedTopics[] = $topic;
    }

    /** @return list<string> */
    public function getPublishedTopics(): array
    {
        return $this->publishedTopics;
    }

    public function getPublishedTopicsCount(): int
    {
        return \count($this->publishedTopics);
    }

    // -----------------------------------------------------------------------
    // ResetInterface
    // -----------------------------------------------------------------------

    public function reset(): void
    {
        $this->jwtCookiePresent = false;
        $this->jwtCookieName = null;
        $this->jwtRefreshIssued = false;
        $this->jwtCookieCleared = false;

        $this->resolvedPath = null;
        $this->resolvedRouteIri = null;
        $this->pageDataFound = false;

        $this->publishedTopics = [];
    }
}
