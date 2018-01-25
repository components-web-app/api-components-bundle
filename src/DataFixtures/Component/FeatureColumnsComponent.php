<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;

class FeatureColumnsComponent extends AbstractFeatureComponent
{
    public static function getComponent(): Component
    {
        return new FeatureColumns();
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var FeatureColumns $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        return $component;
    }
}
