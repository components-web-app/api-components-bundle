<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Navigation;

use Silverback\ApiComponentBundle\Entity\Component\Navigation\AbstractNavigationItem;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Navigation\AbstractNavigationItemFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class AbstractNavigationItemFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = AbstractNavigationItemFactory::class;
        $this->componentClassName = AbstractNavigationItem::class;
        $this->isFinal = false;
        $this->testOps = [
            'className' => 'dummy-class',
            'label' => 'Nav item label',
            'route' => new Route(),
            'fragment' => 'dummy-fragment'
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
