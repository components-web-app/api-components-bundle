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
            $lastItem = $collection->last();

            return $lastItem ? ($lastItem->getSort() + 1) : 0;
        }
        $firstItem = $collection->first();

        return $firstItem ? ($firstItem->getSort() - 1) : 0;
    }

    abstract public function getSortCollection(): ?Collection;
}
