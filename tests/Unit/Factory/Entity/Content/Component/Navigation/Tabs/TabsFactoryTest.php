<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class TabsFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

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