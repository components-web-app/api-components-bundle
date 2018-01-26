<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;

class FeatureStackedComponent extends AbstractComponent
{
    public function getComponent(): Component
    {
        return new FeatureStacked();
    }
}
