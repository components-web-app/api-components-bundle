<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;

class FeatureTextListComponent extends AbstractFeatureComponent
{
    public function getComponent(): Component
    {
        return new FeatureTextList();
    }
}
