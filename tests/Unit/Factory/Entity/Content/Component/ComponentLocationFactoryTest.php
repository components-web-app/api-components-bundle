<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Content\Component;

use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\ComponentLocationFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class ComponentLocationFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = ComponentLocationFactory::class;
        $this->testOps = [
            'component' => $this->getMockForAbstractClass(AbstractComponent::class),
            'content' =>$this->getMockForAbstractClass(AbstractContent::class),
            'dynamicPageClass' => AbstractContent::class
        ];
        parent::setUp();
    }
}
