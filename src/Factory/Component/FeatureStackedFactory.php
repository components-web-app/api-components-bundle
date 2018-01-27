<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;

class FeatureStackedFactory extends AbstractComponentFactory
{
    public function getComponent(): Component
    {
        return new FeatureStacked();
    }
}
