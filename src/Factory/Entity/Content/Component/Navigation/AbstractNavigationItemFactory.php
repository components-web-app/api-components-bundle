<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation;

use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

abstract class AbstractNavigationItemFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            AbstractFactory::COMPONENT_CLASSES,
            [
                'label' => null,
                'route' => null,
                'fragment' => null
            ]
        );
    }
}
