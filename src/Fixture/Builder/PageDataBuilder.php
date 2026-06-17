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

    public function __construct(private readonly AbstractPageData $pageData)
    {
    }

    public function nested(\Closure $configure): void
    {
        $this->nestedClosure = $configure;
    }

    public function getNestedClosure(): ?\Closure
    {
        return $this->nestedClosure;
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
