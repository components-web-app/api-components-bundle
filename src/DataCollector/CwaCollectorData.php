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

    // --- Mercure publications ---
    /** @var list<string> */
    private array $publishedTopics = [];

    // --- Publishable ORM queries ---
    /** @var list<array{class: string, mode: string, queryType: string}> */
    private array $publishableQueries = [];

    // --- PageDataProperty resolutions ---
    /** @var list<array{property: string, resolvedClass: string|null, skipReason: string|null}> */
    private array $pageDataResolutions = [];

    // --- Write invalidation fan-out ---
    /** @var array{created: int, updated: int, deleted: int} */
    private array $invalidationCounts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
    /** @var list<string> */
    private array $cachePurgedIris = [];

    // --- Private Mercure upgrades ---
    /** @var list<array{topics: list<string>, resourceClass: string}> */
    private array $mercurePrivateUpgrades = [];

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
    // Mercure publications
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
    // Publishable ORM queries
    // -----------------------------------------------------------------------

    public function recordPublishableQuery(string $class, string $mode, string $queryType): void
    {
        $this->publishableQueries[] = ['class' => $class, 'mode' => $mode, 'queryType' => $queryType];
    }

    /** @return list<array{class: string, mode: string, queryType: string}> */
    public function getPublishableQueries(): array
    {
        return $this->publishableQueries;
    }

    public function getPublishableQueryCount(): int
    {
        return \count($this->publishableQueries);
    }

    // -----------------------------------------------------------------------
    // PageDataProperty resolutions
    // -----------------------------------------------------------------------

    public function recordPageDataResolution(string $property, ?string $resolvedClass, ?string $skipReason): void
    {
        $this->pageDataResolutions[] = ['property' => $property, 'resolvedClass' => $resolvedClass, 'skipReason' => $skipReason];
    }

    /** @return list<array{property: string, resolvedClass: string|null, skipReason: string|null}> */
    public function getPageDataResolutions(): array
    {
        return $this->pageDataResolutions;
    }

    public function getPageDataResolutionCount(): int
    {
        return \count($this->pageDataResolutions);
    }

    // -----------------------------------------------------------------------
    // Write invalidation fan-out
    // -----------------------------------------------------------------------

    public function recordInvalidationCount(string $type): void
    {
        if (isset($this->invalidationCounts[$type])) {
            ++$this->invalidationCounts[$type];
        }
    }

    /** @return array{created: int, updated: int, deleted: int} */
    public function getInvalidationCounts(): array
    {
        return $this->invalidationCounts;
    }

    public function getTotalInvalidated(): int
    {
        return array_sum($this->invalidationCounts);
    }

    /** @param list<string> $iris */
    public function recordCachePurge(array $iris): void
    {
        $this->cachePurgedIris = array_values(array_unique(array_merge($this->cachePurgedIris, $iris)));
    }

    /** @return list<string> */
    public function getCachePurgedIris(): array
    {
        return $this->cachePurgedIris;
    }

    public function getCachePurgedCount(): int
    {
        return \count($this->cachePurgedIris);
    }

    // -----------------------------------------------------------------------
    // Private Mercure upgrades
    // -----------------------------------------------------------------------

    /** @param list<string> $topics */
    public function recordMercurePrivateUpgrade(array $topics, string $resourceClass): void
    {
        $this->mercurePrivateUpgrades[] = ['topics' => $topics, 'resourceClass' => $resourceClass];
    }

    /** @return list<array{topics: list<string>, resourceClass: string}> */
    public function getMercurePrivateUpgrades(): array
    {
        return $this->mercurePrivateUpgrades;
    }

    public function getMercurePrivateUpgradeCount(): int
    {
        return \count($this->mercurePrivateUpgrades);
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
        $this->publishableQueries = [];
        $this->pageDataResolutions = [];
        $this->invalidationCounts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $this->cachePurgedIris = [];
        $this->mercurePrivateUpgrades = [];
    }
}
