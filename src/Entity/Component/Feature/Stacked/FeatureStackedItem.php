<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;

/**
 * Class FeatureStackedItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureStackedItem extends AbstractFeatureItem
{
    /**
     * @ORM\ManyToOne(targetEntity="FeatureStacked", inversedBy="items")
     * @var FeatureStacked
     */
    protected $feature;
}
