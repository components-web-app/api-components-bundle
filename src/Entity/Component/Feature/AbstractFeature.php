<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class AbstractFeature extends AbstractComponent
{
    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(AbstractFeatureItem::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public function onDeleteCascade(): bool
    {
        return true;
    }
}
