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
interface PublishableInterface
{
    public function setPublishedAt(?\DateTimeInterface $publishedAt);

    public function getPublishedAt(): ?\DateTimeInterface;

    public function isPublished(): bool;

    /** @param static|null $publishedResource */
    public function setPublishedResource($publishedResource);

    /** @return static|null */
    public function getPublishedResource();
}
