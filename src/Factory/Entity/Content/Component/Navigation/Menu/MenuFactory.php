<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\Menu;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MenuFactory extends AbstractComponentFactory
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
}
