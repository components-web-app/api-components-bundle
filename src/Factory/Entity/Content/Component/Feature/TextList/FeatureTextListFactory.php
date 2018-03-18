<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

class FeatureTextListFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureTextList
    {
        $component = new FeatureTextList();
        $this->init($component, $ops);
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
                'title' => null,
                'columns' => 3
            ]
        );
    }
}
