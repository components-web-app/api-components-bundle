<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Feature
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
