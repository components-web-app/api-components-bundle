<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\NavBar;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBarItem;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\AbstractNavigationItemFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class NavBarItemFactory extends AbstractNavigationItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): NavBarItem
    {
        $component = new NavBarItem();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }
}
