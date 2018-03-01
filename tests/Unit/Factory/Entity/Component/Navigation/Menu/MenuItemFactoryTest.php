<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Menu\MenuItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class MenuItemFactoryTest extends AbstractFactory
{
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
