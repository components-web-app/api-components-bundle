<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;

class FeatureColumnsFactory extends AbstractComponentFactory
{
    public function getComponent(): Component
    {
        return new FeatureColumns();
    }
}
