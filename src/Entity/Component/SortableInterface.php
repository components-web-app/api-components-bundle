<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

interface SortableInterface
{
    /**
     * @return int
     */
    public function getSort(): int;

    /**
     * @param int $sort
     * @return SortableTrait
     */
    public function setSort(int $sort = 0): SortableTrait;
}
