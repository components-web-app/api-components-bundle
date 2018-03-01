<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class FeatureColumnsFactory extends AbstractFactory
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
            AbstractFactory::COMPONENT_CLASSES,
            [
                'columns' => null,
                'title' => null
            ]
        );
    }
}
