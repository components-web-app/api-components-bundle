<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

class FeatureTextListFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureTextList
    {
        $component = new FeatureTextList();
        $this->init($component, $ops);
        $component->setTitle($this->ops['title']);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'title' => null
            ]
        );
    }
}
