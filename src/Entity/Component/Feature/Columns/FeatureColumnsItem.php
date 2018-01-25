<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureColumnsItem extends AbstractFeatureItem
{
    /**
     * @ORM\ManyToOne(targetEntity="FeatureColumns", inversedBy="items")
     * @var FeatureColumns
     */
    protected $feature;
}
