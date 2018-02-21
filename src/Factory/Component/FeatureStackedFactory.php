<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;

class FeatureStackedFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new FeatureStacked();
    }
}
