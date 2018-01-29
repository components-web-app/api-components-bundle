<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;

class FeatureColumnsFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new FeatureColumns();
    }
}
