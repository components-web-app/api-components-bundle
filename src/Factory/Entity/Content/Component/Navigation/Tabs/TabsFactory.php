<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TabsFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Tabs
    {
        $component = new Tabs();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return AbstractFactory::COMPONENT_CLASSES;
    }
}
