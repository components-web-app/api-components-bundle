<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class MenuItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = MenuItemFactory::class;
        $this->testOps = [
            'menuLabel' => true,
            'label' => 'Link label'
        ];
        parent::setUp();
    }
}
