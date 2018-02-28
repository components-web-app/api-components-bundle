<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\Menu;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;

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

    /**
     * @inheritdoc
     */
    public static function defaultOps(): array
    {
        return array_merge(
            parent::defaultOps(),
            [
                'title' => 'Untitled',
                'subtitle' => null,
                'tabs' => null
            ]
        );
    }
}
