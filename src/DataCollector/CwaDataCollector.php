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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Symfony profiler/toolbar panel for the CWA API Components Bundle.
 *
 * Shows per-request information across seven categories:
 *   1. JWT / authentication        — cookie presence, refresh, clearance
 *   2. Route resolution            — path header, resolved route IRI, page data
 *   3. Mercure publications        — count and topic list
 *   4. Publishable ORM queries     — draft vs published-only mode per class
 *   5. PageDataProperty resolution — outcome per dynamic slot
 *   6. Write invalidation fan-out  — entity counts and cache-purged IRIs
 *   7. Private Mercure upgrades    — topics upgraded to private for draft resources
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class CwaDataCollector extends DataCollector
{
    public function __construct(private readonly CwaCollectorData $collectorData)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            // JWT
            'jwt_cookie_present' => $this->collectorData->isJwtCookiePresent(),
            'jwt_cookie_name' => $this->collectorData->getJwtCookieName(),
            'jwt_refresh_issued' => $this->collectorData->isJwtRefreshIssued(),
            'jwt_cookie_cleared' => $this->collectorData->isJwtCookieCleared(),

            // Route resolution
            'resolved_path' => $this->collectorData->getResolvedPath(),
            'resolved_route_iri' => $this->collectorData->getResolvedRouteIri(),
            'page_data_found' => $this->collectorData->isPageDataFound(),

            // Mercure publications
            'published_topics' => $this->collectorData->getPublishedTopics(),
            'published_topics_count' => $this->collectorData->getPublishedTopicsCount(),

            // Publishable ORM queries
            'publishable_queries' => $this->collectorData->getPublishableQueries(),
            'publishable_query_count' => $this->collectorData->getPublishableQueryCount(),

            // PageDataProperty resolutions
            'page_data_resolutions' => $this->collectorData->getPageDataResolutions(),
            'page_data_resolution_count' => $this->collectorData->getPageDataResolutionCount(),

            // Write invalidation fan-out
            'invalidation_counts' => $this->collectorData->getInvalidationCounts(),
            'total_invalidated' => $this->collectorData->getTotalInvalidated(),
            'cache_purged_iris' => $this->collectorData->getCachePurgedIris(),
            'cache_purged_count' => $this->collectorData->getCachePurgedCount(),

            // Private Mercure upgrades
            'mercure_private_upgrades' => $this->collectorData->getMercurePrivateUpgrades(),
            'mercure_private_upgrade_count' => $this->collectorData->getMercurePrivateUpgradeCount(),
        ];
    }

    public function getName(): string
    {
        return 'cwa';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->collectorData->reset();
    }

    // -----------------------------------------------------------------------
    // Accessors used in the Twig template
    // -----------------------------------------------------------------------

    public function isJwtCookiePresent(): bool
    {
        return (bool) ($this->data['jwt_cookie_present'] ?? false);
    }

    public function getJwtCookieName(): ?string
    {
        return $this->data['jwt_cookie_name'] ?? null;
    }

    public function isJwtRefreshIssued(): bool
    {
        return (bool) ($this->data['jwt_refresh_issued'] ?? false);
    }

    public function isJwtCookieCleared(): bool
    {
        return (bool) ($this->data['jwt_cookie_cleared'] ?? false);
    }

    public function getResolvedPath(): ?string
    {
        return $this->data['resolved_path'] ?? null;
    }

    public function getResolvedRouteIri(): ?string
    {
        return $this->data['resolved_route_iri'] ?? null;
    }

    public function isPageDataFound(): bool
    {
        return (bool) ($this->data['page_data_found'] ?? false);
    }

    /** @return list<string> */
    public function getPublishedTopics(): array
    {
        return $this->data['published_topics'] ?? [];
    }

    public function getPublishedTopicsCount(): int
    {
        return (int) ($this->data['published_topics_count'] ?? 0);
    }

    /** @return list<array{class: string, mode: string, queryType: string}> */
    public function getPublishableQueries(): array
    {
        return $this->data['publishable_queries'] ?? [];
    }

    public function getPublishableQueryCount(): int
    {
        return (int) ($this->data['publishable_query_count'] ?? 0);
    }

    /** @return list<array{property: string, resolvedClass: string|null, skipReason: string|null}> */
    public function getPageDataResolutions(): array
    {
        return $this->data['page_data_resolutions'] ?? [];
    }

    public function getPageDataResolutionCount(): int
    {
        return (int) ($this->data['page_data_resolution_count'] ?? 0);
    }

    /** @return array{created: int, updated: int, deleted: int} */
    public function getInvalidationCounts(): array
    {
        return $this->data['invalidation_counts'] ?? ['created' => 0, 'updated' => 0, 'deleted' => 0];
    }

    public function getTotalInvalidated(): int
    {
        return (int) ($this->data['total_invalidated'] ?? 0);
    }

    /** @return list<string> */
    public function getCachePurgedIris(): array
    {
        return $this->data['cache_purged_iris'] ?? [];
    }

    public function getCachePurgedCount(): int
    {
        return (int) ($this->data['cache_purged_count'] ?? 0);
    }

    /** @return list<array{topics: list<string>, resourceClass: string}> */
    public function getMercurePrivateUpgrades(): array
    {
        return $this->data['mercure_private_upgrades'] ?? [];
    }

    public function getMercurePrivateUpgradeCount(): int
    {
        return (int) ($this->data['mercure_private_upgrade_count'] ?? 0);
    }
}
