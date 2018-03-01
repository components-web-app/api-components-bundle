<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Menu\Menu;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MenuFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Menu
    {
        $component = new Menu();
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
