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

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait SortableTrait
{
    /** @ORM\Column(type="integer") */
    public ?int $sort = 0;

    final public function calculateSort(?bool $sortLast = null, ?Collection $sortCollection = null): int
    {
        /** @var Collection|SortableInterface[]|null $collection */
        $collection = $sortCollection ?: $this->getSortCollection();

        if (null === $collection || null === $sortLast) {
            return 0;
        }
        if ($sortLast) {
            return $this->getLastSortValue($collection);
        }
        return $this->getFirstSortValue($collection);
    }

    private function isSortableResource($resource): bool
    {
        if (!is_object($resource)) {
            return false;
        }
        return in_array(SortableTrait::class, class_uses($resource), true);
    }

    private function getLastSortValue(Collection $collection): int
    {
        $lastItem = $collection->last();
        return $this->isSortableResource($lastItem) ? ($lastItem->sort + 1) : 0;
    }

    private function getFirstSortValue(Collection $collection): int
    {
        $firstItem = $collection->first();
        return $this->isSortableResource($firstItem) ? ($firstItem->sort - 1) : 0;
    }

    abstract public function getSortCollection(): ?Collection;
}
