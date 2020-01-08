<?php

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
        /* @var $collection Collection|SortableInterface[]|null */
        $collection = $sortCollection ?: $this->getSortCollection();

        if ($collection === null || $sortLast === null) {
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
