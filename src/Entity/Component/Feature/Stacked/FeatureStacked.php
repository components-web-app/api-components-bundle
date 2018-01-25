<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
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
}
