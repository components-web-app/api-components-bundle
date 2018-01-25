<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;

class FeatureStackedComponent extends AbstractFeatureComponent
{
    public static function getComponent(): Component
    {
        return new FeatureStacked();
    }

    public static function defaultOps(): array
    {
        return [];
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var FeatureStacked $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        return $component;
    }
}
