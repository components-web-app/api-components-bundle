<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\TextList;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureTextList
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureTextList extends AbstractFeature
{
    /**
     * @ORM\OneToMany(targetEntity="FeatureTextListItem", mappedBy="feature")
     * @ORM\OrderBy({"sortOrder" = "ASC"})
     * @Groups({"page"})
     */
    protected $items;

    /**
     * @ORM\Column(type="Number")
     * @var int
     */
    protected $columns = 3;

    /**
     * @return FeatureItemInterface
     */
    public function createItem(): FeatureItemInterface
    {
        return new FeatureTextListItem();
    }

    /**
     * @return int
     */
    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * @param int $columns
     */
    public function setColumns(int $columns): void
    {
        $this->columns = $columns;
    }
}
