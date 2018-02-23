<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class AbstractComponentFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = AbstractComponentFactory::class;
        $this->componentClassName = AbstractComponent::class;
        $this->isFinal = false;
        $this->testOps = [
            'className' => 'dummy-class'
        ];
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
