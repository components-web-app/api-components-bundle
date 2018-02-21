<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;

class FeatureTextListFactory extends AbstractComponentFactory
{
    public function getComponent(): Component
    {
        return new FeatureTextList();
    }
}
