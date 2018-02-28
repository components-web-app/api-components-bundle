<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\ValidComponentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;

class ValidComponentTraitTest extends TestCase
{
    /**
     * @var MockObject|ValidComponentTrait
     */
    private $validComponentTraitMock;

    public function setUp()
    {
        $this->validComponentTraitMock = $this->getMockForTrait(ValidComponentTrait::class);
    }

    public function test_valid_components()
    {
        $this->validComponentTraitMock->addValidComponent('ComponentClass');
        $this->validComponentTraitMock->addValidComponent('ComponentClass');
        $validComponents = $this->validComponentTraitMock->getValidComponents();
        $this->assertCount(1, $validComponents);
        $this->assertEquals('ComponentClass', $validComponents->first());

        $this->validComponentTraitMock->removeValidComponent('ComponentClass');
        $this->assertCount(0, $this->validComponentTraitMock->getValidComponents());
    }

    /**
     * @throws \ReflectionException
     */
    public function test_valid_components_cascade()
    {
        $parentComponentClass = 'ParentComponentClass';

        /** @var MockObject|ValidComponentTrait $originalValidComponentMock */
        $originalValidComponentMock = $this->getMockBuilder(ValidComponentInterface::class)->getMock();
        $originalValidComponentMock->method('getValidComponents')->willReturn(new ArrayCollection([$parentComponentClass]));
        $method = $this->getCascadeMethod();

        $this->validComponentTraitMock->addValidComponent('AnyComponentClass');
        $method->invokeArgs(
            $this->validComponentTraitMock,
            [
                $originalValidComponentMock
            ]
        );

        $validComponents = $this->validComponentTraitMock->getValidComponents();
        $this->assertCount(1, $validComponents);
        $this->assertEquals($parentComponentClass, $validComponents->first());
    }

    /**
     * @throws \ReflectionException
     */
    public function test_valid_components_cascade_empty_force_option()
    {
        $anyComponentClass = 'AnyComponentClass';

        /** @var MockObject|ValidComponentTrait $originalValidComponentMock */
        $originalValidComponentMock = $this->getMockBuilder(ValidComponentInterface::class)->getMock();
        $originalValidComponentMock->method('getValidComponents')->willReturn(new ArrayCollection());
        $method = $this->getCascadeMethod();

        $this->validComponentTraitMock->addValidComponent($anyComponentClass);
        $method->invokeArgs(
            $this->validComponentTraitMock,
            [
                $originalValidComponentMock,
                false
            ]
        );

        $validComponents = $this->validComponentTraitMock->getValidComponents();
        $this->assertCount(1, $validComponents);
        $this->assertEquals($anyComponentClass, $validComponents->first());

        $method->invokeArgs(
            $this->validComponentTraitMock,
            [
                $originalValidComponentMock,
                true
            ]
        );
        $this->assertCount(0, $this->validComponentTraitMock->getValidComponents());
    }

    /**
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    private function getCascadeMethod()
    {
        $class = new \ReflectionClass($this->validComponentTraitMock);
        $method = $class->getMethod('cascadeValidComponents');
        $method->setAccessible(true);
        return $method;
    }
}
