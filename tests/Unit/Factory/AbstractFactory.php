<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Factory\Entity\Component\AbstractComponentFactory;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFactory extends TestCase
{
    /**
     * @var MockObject|AbstractComponentFactory
     */
    protected $factory;
    /**
     * @var \ReflectionClass
     */
    protected $reflection;

    /**
     * @var MockObject|ObjectRepository
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $componentClassName;

    /**
     * @var array
     */
    protected $testOps = [];

    /**
     * @var bool
     */
    protected $isFinal = true;

    /**
     * @var MockObject|ValidatorInterface
     */
    private $validator;

    /**
     * @throws \ReflectionException
     */
    public function setUp()
    {
        $constructorArgs = $this->getConstructorArgs();
        $this->objectManager = $constructorArgs[0];
        if ($this->isFinal) {
            $this->factory = new $this->className(...$constructorArgs);
        } else {
            $this->factory = $this
                ->getMockForAbstractClass($this->className, $constructorArgs)
            ;
        }
        $this->reflection = new \ReflectionClass($this->className);
    }

    public function test_default_options(): void
    {
        $this->runDefaultOpsTest();
    }

    public function test_process(): void
    {
        if ($this->isFinal) {
            $this->runCreateTest();
        } else {
            $this->runInitTest();
        }
    }

    protected function getConstructorArgs(): array
    {
        /** @var ObjectManager $objectManagerMock */
        $objectManagerMock = $this
            ->getMockBuilder(ObjectManager::class)
            ->getMock()
        ;

        $this->validator = $this
            ->getMockBuilder(ValidatorInterface::class)
            ->getMock()
        ;
        $this->validator
            ->method('validate')
            ->will($this->returnValue(new ConstraintViolationList()))
        ;

        return [
            $objectManagerMock,
            $this->validator
        ];
    }

    protected function runDefaultOpsTest(): void
    {
        $method = $this->reflection->getMethod('defaultOps');
        $method->setAccessible(true);
        $defaultOps = $method->invoke($this->factory);
        $ops = array_keys($this->testOps);
        array_unshift($ops, 'className');
        foreach ($ops as $key) {
            $this->assertArrayHasKey($key, $defaultOps);
        }
    }

    protected function runInitTest(): void
    {
        $component = $this
            ->getMockBuilder($this->componentClassName)
            ->getMock()
        ;
        $initMethod = $this->reflection->getMethod('init');
        $initMethod->setAccessible(true);

        $this->objectManager
            ->expects($this->once())
            ->method('persist')
        ;

        foreach ($this->testOps as $key=>$op) {
            $component
                ->expects($this->once())
                ->method('set' . ucfirst($key))
                ->with($op)
            ;
        }

        $initMethod->invokeArgs(
            $this->factory,
            [
                $component,
                $this->testOps
            ]
        );
    }

    protected function runCreateTest(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('validate')
        ;
        $component = $this->factory->create($this->testOps);
        foreach ($this->testOps as $key=>$op) {
            $getter = 'get' . ucfirst($key);
            if (!method_exists($component, $getter)) {
                $getter = 'is' . ucfirst($key);
            }
            $this->assertEquals($op, $component->$getter());
        }
    }
}
