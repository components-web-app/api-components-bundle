<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use Doctrine\Common\Collections\Collection;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface SortableInterface
{
    public function calculateSort(?bool $sortLast = null, ?Collection $sortCollection = null): int;
    public function getSortCollection(): ?Collection;
}
