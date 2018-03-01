<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation\Tabs;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\Tabs\TabsItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class TabsItemFactoryTest extends AbstractFactory
{
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
