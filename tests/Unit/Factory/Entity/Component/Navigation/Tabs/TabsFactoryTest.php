<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Tabs\TabsFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class TabsFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = TabsFactory::class;
        $this->testOps = [];
        parent::setUp();
    }
}