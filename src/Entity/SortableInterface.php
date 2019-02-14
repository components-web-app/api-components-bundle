<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\Collection;

interface SortableInterface
{
    public function getSort(): ?int;

    public function setSort(int $sort = 0);

    public function calculateSort(?bool $sortLast = null, ?Collection $sortCollection = null): int;

    public function getSortCollection(): ?Collection;
}
