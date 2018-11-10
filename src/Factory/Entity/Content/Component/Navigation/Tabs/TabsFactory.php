<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TabsFactory extends AbstractComponentFactory
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
}
