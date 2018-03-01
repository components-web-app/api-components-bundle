<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Stacked\FeatureStackedItem;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\AbstractFeatureItemFactory;

class FeatureStackedItemFactory extends AbstractFeatureItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureStackedItem
    {
        $component = new FeatureStackedItem();
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
                'description' => null,
                'buttonText' => null,
                'buttonClass' => null
            ]
        );
    }
}
