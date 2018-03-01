<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature;

use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

abstract class AbstractFeatureItemFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            AbstractFactory::COMPONENT_CLASSES,
            [
                'label' => '',
                'link' => null
            ]
        );
    }
}
