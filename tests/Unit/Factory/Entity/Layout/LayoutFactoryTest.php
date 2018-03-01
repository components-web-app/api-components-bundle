<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Layout;

use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar;
use Silverback\ApiComponentBundle\Factory\Entity\Layout\LayoutFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class LayoutFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = LayoutFactory::class;
        $this->testOps = [
            'default' => true,
            'navBar' => $this->getMockBuilder(NavBar::class)->getMock()
        ];
        parent::setUp();
    }
}
