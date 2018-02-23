<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait SortableTrait
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 */
trait SortableTrait
{
    /**
     * @ORM\Column(type="integer", nullable=false)
     * @Assert\NotNull()
     * @var int
     */
    protected $sort;

    /**
     * @return int
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * @param int $sort
     * @return SortableInterface|SortableTrait
     */
    public function setSort(int $sort = 0)
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @param bool|null $sortLast
     * @return int
     */
    final public function calculateSort(?bool $sortLast = null): int
    {
        /* @var $collection Collection|SortableInterface[] */
        $collection = $this->getSortCollection();
        if (null === $sortLast) {
            return 0;
        }
        if ($sortLast) {
            $lastItem = $collection->last();
            return $lastItem ? ($lastItem->getSort() + 1) : 0;
        }
        $firstItem = $collection->first();
        return $firstItem ? ($firstItem->getSort() - 1) : 0;
    }

    /**
     * @return ArrayCollection
     */
    abstract public function getSortCollection(): ArrayCollection;
}
