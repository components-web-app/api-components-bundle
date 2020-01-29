<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait SortableTrait
{
    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     * @var int|null
     */
    protected $sort;

    /**
     * @return int|null
     */
    public function getSort(): ?int
    {
        return $this->sort;
    }

    /**
     * @param int|null $sort
     * @return static
     */
    public function setSort(?int $sort = 0)
    {
        if ($sort === null) { $sort = 0; }
        $this->sort = $sort;
        return $this;
    }

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

    /**
     * @return Collection|null
     */
    abstract public function getSortCollection(): ?Collection;
}
