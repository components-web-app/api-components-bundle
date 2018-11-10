<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\AbstractFeatureItemFactory;

class FeatureColumnsItemFactory extends AbstractFeatureItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureColumnsItem
    {
        $component = new FeatureColumnsItem();
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
                'filePath' => null
            ]
        );
    }
}
