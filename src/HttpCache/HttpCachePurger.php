<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\PersistentCollection;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;

class HttpCachePurger implements ResourceChangedPropagatorInterface
{
    public const string RENDERED_HTML_TAG = 'cwa-html';

    private array $tags;
    private bool $purgeRenderedHtml;

    /**
     * @param array<class-string> $purgeRenderedHtmlClasses
     */
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
        private readonly ?PurgerInterface $httpCachePurger,
        private readonly ?CwaCollectorData $collectorData = null,
        private readonly array $purgeRenderedHtmlClasses = [],
        private readonly ?ManifestKeyResolver $manifestKeyResolver = null,
    ) {
        $this->reset();
    }

    public function add(object $item, ?string $type = null): void
    {
        if (!is_iterable($item)) {
            $this->collectResource($item);

            return;
        }

        if ($item instanceof PersistentCollection) {
            $item = clone $item;
        }

        foreach ($item as $i) {
            $this->collectResource($i);
        }
    }

    public function collectResource($entity): void
    {
        if (null === $entity) {
            return;
        }

        try {
            $resourceClass = $this->resourceClassResolver->getResourceClass($entity);

            $this->collectRenderedHtml($resourceClass);

            $this->collectManifestKeys($entity);

            $resourceIri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
            $this->collectIri($resourceIri);

            $this->collectItem($entity);
        } catch (OperationNotFoundException|InvalidArgumentException) {
        }
    }

    private function collectItem($item): void
    {
        try {
            $iri = $this->iriConverter->getIriFromResource($item);
            $this->collectIri($iri);
        } catch (InvalidArgumentException|RuntimeException) {
        }
    }

    private function collectIri($iri): void
    {
        if (!\in_array($iri, $this->tags, true)) {
            $this->tags[$iri] = $iri;
        }
    }

    private function collectManifestKeys(object $entity): void
    {
        if (null === $this->manifestKeyResolver) {
            return;
        }

        foreach ($this->manifestKeyResolver->resolve($entity) as $iri) {
            $this->collectIri(CwaTagCollector::MANIFEST_TAG_PREFIX . $iri);
        }
    }

    private function collectRenderedHtml(string $resourceClass): void
    {
        if ($this->purgeRenderedHtml) {
            return;
        }

        foreach ($this->purgeRenderedHtmlClasses as $purgeRenderedHtmlClass) {
            if (is_a($resourceClass, $purgeRenderedHtmlClass, true)) {
                $this->purgeRenderedHtml = true;

                return;
            }
        }
    }

    public function propagate(): void
    {
        $iris = array_values($this->tags);
        if ($this->purgeRenderedHtml) {
            $iris[] = self::RENDERED_HTML_TAG;
        }

        if (empty($iris)) {
            return;
        }

        $this->send($iris);
        $this->reset();
    }

    public function purgeRenderedHtml(): void
    {
        $this->send([self::RENDERED_HTML_TAG]);
    }

    private function send(array $iris): void
    {
        $this->collectorData?->recordCachePurge($iris);
        $this->httpCachePurger && $this->httpCachePurger->purge($iris);
    }

    public function reset(): void
    {
        $this->tags = [];
        $this->purgeRenderedHtml = false;
    }
}
