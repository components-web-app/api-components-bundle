<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;

class FeatureTextListFactory extends AbstractComponentFactory
{
    public function getComponent(): AbstractComponent
    {
        return new FeatureTextList();
    }
}
