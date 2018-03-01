<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Navigation\Tabs\TabsItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class TabsItemFactoryTest extends AbstractFactory
{
    protected $presets = ['component'];

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = TabsItemFactory::class;
        $this->testOps = [
            'label' => 'Dummy label'
        ];
        parent::setUp();
    }
}
