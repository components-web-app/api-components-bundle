<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\NavBar;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class NavBarFactory extends AbstractComponentFactory
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
}
