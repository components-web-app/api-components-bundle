<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureStacked
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureMedia
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class FeatureStacked extends AbstractFeature
{
    /**
     * @ORM\Column(type="boolean")
     * @Groups({"component"})
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
