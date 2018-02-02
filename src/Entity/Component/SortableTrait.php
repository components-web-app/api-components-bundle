<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

trait SortableTrait
{
    /**
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
     * @return SortableTrait
     */
    public function setSort(int $sort = 0): SortableTrait
    {
        $this->sort = $sort;
        return $this;
    }
}
