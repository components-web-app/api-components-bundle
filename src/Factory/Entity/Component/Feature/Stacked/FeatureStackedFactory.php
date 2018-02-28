<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

class FeatureStackedFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureStacked
    {
        $component = new FeatureStacked();
        $this->init($component, $ops);
        $component->setReverse($this->ops['reverse']);
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
