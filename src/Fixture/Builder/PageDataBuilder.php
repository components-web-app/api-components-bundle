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
        return $this->pageData->getRoute();
    }
}
