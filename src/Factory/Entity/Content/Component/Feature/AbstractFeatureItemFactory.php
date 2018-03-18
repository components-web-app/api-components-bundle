<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Feature;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

abstract class AbstractFeatureItemFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'label' => '',
                'url' => null,
                'route' => null
            ]
        );
    }
}
