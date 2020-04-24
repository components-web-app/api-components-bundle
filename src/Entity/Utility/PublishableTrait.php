<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
trait PublishableTrait
{
    private ?\DateTimeInterface $publishedAt = null;

    private ?self $publishedResource = null;

    private ?self $draftResource = null;

    /** @return static */
    public function setPublishedAt(?\DateTimeInterface $publishedAt)
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt && new \DateTimeImmutable() >= $this->publishedAt;
    }

    /** @return static */
    public function setPublishedResource($publishedResource)
    {
        $this->publishedResource = $publishedResource;

        return $this;
    }

    public function getPublishedResource(): ?self
    {
        return $this->publishedResource;
    }

    public function getDraftResource(): ?self
    {
        return $this->draftResource;
    }
}
