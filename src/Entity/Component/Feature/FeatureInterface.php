<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

interface FeatureInterface
{
    /**
     * @return FeatureItemInterface
     */
    public function createItem(): FeatureItemInterface;
}
