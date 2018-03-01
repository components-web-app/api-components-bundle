<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\NavBar;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\NavBar\NavBarFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class NavBarFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = NavBarFactory::class;
        $this->testOps = [];
        parent::setUp();
    }
}