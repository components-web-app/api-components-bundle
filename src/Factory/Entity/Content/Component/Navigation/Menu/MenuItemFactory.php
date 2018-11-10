<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\MenuItem;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\AbstractNavigationItemFactory;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MenuItemFactory extends AbstractNavigationItemFactory
{
    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): MenuItem
    {
        $component = new MenuItem();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'menuLabel' => false
            ]
        );
    }
}
