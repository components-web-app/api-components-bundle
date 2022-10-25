<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\PersistentCollection;

class HttpCachePurger implements ResourceChangedPropagatorInterface
{
    private array $tags;

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
        private readonly PurgerInterface $httpCachePurger,
    ) {
        $this->reset();
    }

    public function collectResource($entity, ?string $type = null): void
    {
        if (null === $entity) {
            return;
        }

        try {
            $resourceClass = $this->resourceClassResolver->getResourceClass($entity);
            $resourceIri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
            $this->collectIri([$resourceIri]);
        } catch (OperationNotFoundException|InvalidArgumentException $e) {
        }
    }

    public function collectItems($items, ?string $type = null): void
    {
        if (!$items) {
            return;
        }

        if (!is_iterable($items)) {
            $this->collectItem($items);

            return;
        }

        if ($items instanceof PersistentCollection) {
            $items = clone $items;
        }

        foreach ($items as $i) {
            $this->collectItem($i);
        }
    }

    private function collectItem($item): void
    {
        try {
            $iri = $this->iriConverter->getIriFromResource($item);
            $this->collectIri([$iri]);
        } catch (InvalidArgumentException|RuntimeException $e) {
        }
    }

    private function collectIri(array $iris): void
    {
        foreach ($iris as $iri) {
            if (!\in_array($iri, $this->tags, true)) {
                $this->tags[$iri] = $iri;
            }
        }
    }

    public function propagate(): void
    {
        if (empty($this->tags)) {
            return;
        }

        $this->httpCachePurger->purge(array_values($this->tags));
        $this->reset();
    }

    public function reset(): void
    {
        $this->tags = [];
    }
}
