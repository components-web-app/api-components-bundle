<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\Component;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class Feature extends Component implements FeatureInterface
{
    /**
     * @var Collection|FeatureItem[]
     */
    private $items;

    public function getItems()
    {
        return $this->items;
    }
}
