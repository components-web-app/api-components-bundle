<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\AbstractFeatureItemFactory;

class FeatureTextListItemFactory extends AbstractFeatureItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureTextListItem
    {
        $component = new FeatureTextListItem();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }
}
