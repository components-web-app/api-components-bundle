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
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait SortableTrait
{
    private ?PropertyAccessor $propertyAccessor;

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

    private function getLastSortValue(Collection $collection): int
    {
        $lastItem = $collection->last();
        return ($sortValue = $this->getSortValue($lastItem)) ? ($sortValue + 1) : 0;
    }

    private function getFirstSortValue(Collection $collection): int
    {
        $firstItem = $collection->first();
        return ($sortValue = $this->getSortValue($firstItem)) ? ($sortValue - 1) : 0;
    }

    private function getSortValue($object)
    {
        if (!$this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
        return $this->propertyAccessor->getValue($object, 'sort');
    }

    abstract public function getSortCollection(): ?Collection;
}
