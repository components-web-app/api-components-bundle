<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Stacked\FeatureStacked;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

class FeatureStackedFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureStacked
    {
        $component = new FeatureStacked();
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
                'reverse' => false
            ]
        );
    }
}
