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
 * Shows three categories of per-request information:
 *   1. JWT / authentication — cookie presence, refresh, clearance
 *   2. Route resolution     — path header, resolved route IRI, page data
 *   3. Mercure publications — count and topic list
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

            // Mercure
            'published_topics' => $this->collectorData->getPublishedTopics(),
            'published_topics_count' => $this->collectorData->getPublishedTopicsCount(),
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
}
