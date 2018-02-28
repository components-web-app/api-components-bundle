<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

class FeatureColumnsFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureColumns
    {
        $component = new FeatureColumns();
        $this->init($component, $ops);
        $component->setTitle($this->ops['title']);
        if ($this->ops['columns']) {
            $component->setColumns($this->ops['columns']);
        }
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
