<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

class FeatureTextListFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): FeatureTextList
    {
        $component = new FeatureTextList();
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
                'title' => null
            ]
        );
    }
}
