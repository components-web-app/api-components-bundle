<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\AbstractNavigationItem;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

abstract class AbstractNavigationItemFactory extends AbstractComponentFactory
{
    /**
     * @inheritdoc
     * @param AbstractNavigationItem $component
     */
    protected function init($component, ?array $ops = null): void
    {
        parent::init($component, $ops);
        $component->setLabel($this->ops['label']);
        $component->setRoute($this->ops['route']);
        $component->setFragment($this->ops['fragment']);
    }

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
