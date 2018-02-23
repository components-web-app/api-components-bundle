<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\AbstractFeatureItemFactory;

class FeatureColumnsItemFactory extends AbstractFeatureItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureColumnsItem
    {
        $component = new FeatureColumnsItem();
        $this->init($component, $ops);
        $component->setDescription($this->ops['description']);
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
                'description' => null
            ]
        );
    }
}
