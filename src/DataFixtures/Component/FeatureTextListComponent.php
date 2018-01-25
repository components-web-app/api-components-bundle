<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;

class FeatureTextListComponent extends AbstractFeatureComponent
{
    public static function getComponent(): Component
    {
        return new FeatureTextList();
    }

    public static function defaultOps(): array
    {
        return [];
    }

    public function create($owner, array $ops = null): Component
    {
        /**
         * @var FeatureTextList $component
         */
        $ops = self::processOps($ops);
        $component = parent::create($owner, $ops);
        return $component;
    }
}
