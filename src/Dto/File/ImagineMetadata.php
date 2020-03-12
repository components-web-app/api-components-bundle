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

namespace Silverback\ApiComponentBundle\Dto\File;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineMetadata
{
    private ArrayCollection $filters;

    public function __construct()
    {
        $this->filters = new ArrayCollection();
    }

    public function addFilter(string $key, ImageMetadata $imageMetadata): self
    {
        $this->filters->set($key, $imageMetadata);

        return $this;
    }

    public function removeFilter(string $key): self
    {
        $this->filters->remove($key);

        return $this;
    }

    public function setFilters(array $filters)
    {
        $this->filters = new ArrayCollection();
        foreach ($filters as $key => $filter) {
            $this->addFilter($key, $filter);
        }
    }

    public function getFilters()
    {
        return $this->filters;
    }
}
