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

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\HttpCache\PurgerInterface;
use Doctrine\ORM\PersistentCollection;

/**
 * Purges desired resources on when doctrine is flushed from the proxy cache.
 *
 * @author Daniel West <daniel@silverback.is>
 *
 * @experimental
 */
class PurgeHttpCacheListener
{
    private PurgerInterface $purger;
    private IriConverterInterface $iriConverter;
    private array $tags = [];

    public function __construct(PurgerInterface $purger, IriConverterInterface $iriConverter)
    {
        $this->purger = $purger;
        $this->iriConverter = $iriConverter;
    }

    /**
     * Purges tags collected during this request, and clears the tag list.
     *
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    public function postFlush(): void
    {
        if (empty($this->tags)) {
            return;
        }

        $this->purger->purge(array_values($this->tags));
        $this->tags = [];
    }

    /**
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    public function addTagsFor($value): void
    {
        if (!$value) {
            return;
        }

        if (!is_iterable($value)) {
            $this->addTagForItem($value);

            return;
        }

        if ($value instanceof PersistentCollection) {
            $value = clone $value;
        }

        foreach ($value as $v) {
            $this->addTagForItem($v);
        }
    }

    /**
     * @see \ApiPlatform\Doctrine\EventListener\PurgeHttpCacheListener
     */
    private function addTagForItem($value): void
    {
        try {
            $iri = $this->iriConverter->getIriFromItem($value);
            $this->tags[$iri] = $iri;
        } catch (InvalidArgumentException $e) {
        } catch (RuntimeException $e) {
        }
    }
}
