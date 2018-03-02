<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\ProxyInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Tests\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ClassNameValidatorTest extends TestCase
{
    /**
     * @var FileComponent
     */
    private $class;
    /**
     * @var ProxyInterface
     */
    private $proxy;

    public function setUp()
    {
        $this->class = new FileComponent();
        $factory = new LazyLoadingValueHolderFactory();
        $this->proxy = $factory->createProxy(
            FileComponent::class,
            function (&$wrappedObject) {
                $wrappedObject = new FileComponent();
            }
        );
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_success(): void
    {

        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->class));
        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->proxy));
        $this->assertTrue(ClassNameValidator::isClassSame(FileInterface::class, $this->class));
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_invalid_classname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClassNameValidator::isClassSame('NotAClass', $this->class);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_invalid_valid_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClassNameValidator::isClassSame(FileComponent::class, 'NotAnObject');
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_fail(): void
    {
        $this->assertFalse(ClassNameValidator::isClassSame(TestHandler::class, $this->class));
    }

    /**
     * @throws \ReflectionException
     */
    public function test_validate(): void
    {
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, $this->proxy]));
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, 'NotAnObject']));
        $this->assertFalse(ClassNameValidator::validate(TestHandler::class, [$this->class]));
    }
}
