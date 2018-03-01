<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Menu\MenuFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class MenuFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = MenuFactory::class;
        $this->testOps = [];
        parent::setUp();
    }
}
