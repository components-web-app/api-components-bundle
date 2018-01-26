<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureStacked
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureMedia
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureStacked extends AbstractFeature
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem", mappedBy="feature")
     * @ORM\OrderBy({"sortOrder" = "ASC"})
     * @Groups({"page"})
     */
    protected $items;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"page"})
     * @var bool
     */
    protected $reverse = false;

    /**
     * @return FeatureItemInterface
     */
    public function createItem(): FeatureItemInterface
    {
        return new FeatureStackedItem();
    }

    /**
     * @return bool
     */
    public function isReverse(): bool
    {
        return $this->reverse;
    }

    /**
     * @param bool $reverse
     */
    public function setReverse(bool $reverse): void
    {
        $this->reverse = $reverse;
    }
}
