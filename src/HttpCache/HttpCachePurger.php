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

use ApiPlatform\Exception\InvalidArgumentException as LegacyInvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException as LegacyOperationNotFoundException;
use ApiPlatform\Exception\RuntimeException as LegacyRuntimeException;
use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
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

            // collect cache of any collections being fetched
            $resourceIri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));
            $this->collectIri($resourceIri);

            // clear cache of anything containing this item
            $this->collectItem($entity);
        } catch (OperationNotFoundException|InvalidArgumentException|LegacyOperationNotFoundException|LegacyInvalidArgumentException) {
        }
    }

    private function collectItem($item): void
    {
        try {
            $iri = $this->iriConverter->getIriFromResource($item);
            $this->collectIri($iri);
        } catch (InvalidArgumentException|RuntimeException|LegacyInvalidArgumentException|LegacyRuntimeException) {
        }
    }

    private function collectIri($iri): void
    {
        if (!\in_array($iri, $this->tags, true)) {
            $this->tags[$iri] = $iri;
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
