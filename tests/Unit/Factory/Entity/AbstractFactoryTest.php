<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity;

use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Entity\AbstractFactory as AbstractFactoryEntity;

class AbstractFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = AbstractFactoryEntity::class;
        $this->componentClassName = AbstractComponent::class;
        $this->isFinal = false;
        $this->testOps = [];
        parent::setUp();
    }

    public function test_invalid_option_handling(): void
    {
        $this->expectException(InvalidFactoryOptionException::class);
        $method = $this->reflection->getMethod('setOptions');
        $method->setAccessible(true);
        $method->invokeArgs($this->factory, [[ 'ops' => null ]]);
    }
}
