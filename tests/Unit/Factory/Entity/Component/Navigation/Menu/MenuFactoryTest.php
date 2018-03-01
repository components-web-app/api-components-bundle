<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\Menu;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Menu\MenuFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class MenuFactoryTest extends AbstractFactory
{
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
