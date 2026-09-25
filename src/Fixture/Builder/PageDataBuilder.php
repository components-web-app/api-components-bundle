<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Builder;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

class PageDataBuilder
{
    private ?\Closure $nestedClosure = null;
    private ?\Closure $onRoutesCreated = null;
    private array $childPageRefs = [];
    private bool $hasLiveAt = false;
    private ?\DateTimeImmutable $liveAt = null;
    private bool $withoutRoute = false;
    private ?AbstractPageData $existingPageData = null;

    public function __construct(private readonly AbstractPageData $pageData)
    {
    }

    public function nested(\Closure $configure): self
    {
        $this->nestedClosure = $configure;

        return $this;
    }

    public function getNestedClosure(): ?\Closure
    {
        return $this->nestedClosure;
    }

    public function liveAt(?\DateTimeImmutable $liveAt): self
    {
        $this->hasLiveAt = true;
        $this->liveAt = $liveAt;

        return $this;
    }

    public function hasLiveAt(): bool
    {
        return $this->hasLiveAt;
    }

    public function getLiveAt(): ?\DateTimeImmutable
    {
        return $this->liveAt;
    }

    public function onRoutesCreated(\Closure $cb): self
    {
        $this->onRoutesCreated = $cb;

        return $this;
    }

    public function getOnRoutesCreated(): ?\Closure
    {
        return $this->onRoutesCreated;
    }

    public function setChildPageRefs(array $refs): void
    {
        $this->childPageRefs = $refs;
    }

    public function getChildPageRefs(): array
    {
        return $this->childPageRefs;
    }

    public function getPageData(): AbstractPageData
    {
        return $this->pageData;
    }

    public function getRoute(): ?Route
    {
        return ($this->existingPageData ?? $this->pageData)->getRoute();
    }

    public function withoutRoute(): self
    {
        $this->withoutRoute = true;

        return $this;
    }

    public function isWithoutRoute(): bool
    {
        return $this->withoutRoute;
    }

    /**
     * @internal
     */
    public function setExistingPageData(AbstractPageData $pageData): void
    {
        $this->existingPageData = $pageData;
    }
}
