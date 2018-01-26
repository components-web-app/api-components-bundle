<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;

class FeatureColumnsComponent extends AbstractComponent
{
    public function getComponent(): Component
    {
        return new FeatureColumns();
    }
}
