<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait SortableTrait
{
    /**
     * @ORM\Column(type="smallint", nullable=true)
     * @Groups({"page"})
     * @var null|int
     */
    private $sort;

    /**
     * @return int|null
     */
    public function getSort(): ?int
    {
        return $this->sort;
    }

    /**
     * @param int|null $sort
     */
    public function setSort(?int $sort): void
    {
        $this->sort = $sort;
    }
}
