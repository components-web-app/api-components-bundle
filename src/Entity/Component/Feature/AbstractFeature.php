<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class AbstractFeature extends AbstractComponent implements FeatureInterface
{
    /**
     * @var Collection|AbstractFeatureItem[]
     */
    private $items;

    public function getItems()
    {
        return $this->items;
    }
}
