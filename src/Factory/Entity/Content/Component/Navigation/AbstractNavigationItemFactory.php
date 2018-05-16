<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

abstract class AbstractNavigationItemFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'label' => null,
                'route' => null,
                'fragment' => null
            ]
        );
    }
}
