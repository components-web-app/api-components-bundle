<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class NavBarFactory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): NavBar
    {
        $component = new NavBar();
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
