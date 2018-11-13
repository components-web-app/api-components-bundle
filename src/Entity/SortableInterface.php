<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface SortableInterface
{
    /**
     * @return int|null
     */
    public function getSort(): ?int;

    /**
     * @param int $sort
     * @return SortableTrait|SortableInterface
     */
    public function setSort(int $sort = 0);

    /**
     * @param bool|null $sortLast
     * @return int
     */
    public function calculateSort(?bool $sortLast = null): int;

    /**
     * @return Collection|SortableInterface[]
     */
    public function getSortCollection(): Collection;
}
