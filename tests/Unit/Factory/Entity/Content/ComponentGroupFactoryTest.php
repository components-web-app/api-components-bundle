<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content;

use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Factory\Entity\Content\ComponentGroupFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class ComponentGroupFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = ComponentGroupFactory::class;
        $this->testOps = [
            'parent' => $this->getMockForAbstractClass(AbstractComponent::class)
        ];
        parent::setUp();
    }
}
