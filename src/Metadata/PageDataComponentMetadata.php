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

namespace Silverback\ApiComponentsBundle\Metadata;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataComponentMetadata
{
    private array $pageDataResources;
    private ArrayCollection $properties;

    public function __construct(array $pageDataResources, ArrayCollection $properties)
    {
        $this->pageDataResources = $pageDataResources;
        $this->properties = $properties;
    }

    public function getPageDataResources(): array
    {
        return $this->pageDataResources;
    }

    public function getProperties(): ArrayCollection
    {
        return $this->properties;
    }
}
