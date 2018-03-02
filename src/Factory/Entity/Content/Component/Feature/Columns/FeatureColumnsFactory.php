<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

class FeatureColumnsFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureColumns
    {
        $component = new FeatureColumns();
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
                'columns' => null,
                'title' => null
            ]
        );
    }
}
