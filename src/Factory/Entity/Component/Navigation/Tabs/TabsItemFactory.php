<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\TabsItem;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\AbstractNavigationItemFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TabsItemFactory extends AbstractNavigationItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): TabsItem
    {
        $component = new TabsItem();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }
}
