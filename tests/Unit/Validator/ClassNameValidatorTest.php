<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\ProxyInterface;
use ReflectionException;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ClassNameValidatorTest extends TestCase
{
    private FileComponent $class;

    private ProxyInterface $proxy;

    protected function setUp()
    {
        $this->class = new FileComponent();
        $factory = new LazyLoadingValueHolderFactory(new Configuration());
        $this->proxy = $factory->createProxy(
            FileComponent::class,
            function (&$wrappedObject) {
                $wrappedObject = new FileComponent();
            }
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testClassSameValidationSuccess(): void
    {
        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->class));
        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->proxy));
        $this->assertTrue(ClassNameValidator::isClassSame(FileInterface::class, $this->class));
    }

    /**
     * @throws ReflectionException
     */
    public function testClassSameValidationInvalidClassname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClassNameValidator::isClassSame('NotAClass', $this->class);
    }

    /**
     * @throws ReflectionException
     */
    public function testClassSameValidationFail(): void
    {
        $this->assertFalse(ClassNameValidator::isClassSame(TestHandler::class, $this->class));
    }

    /**
     * @throws ReflectionException
     */
    public function testValidate(): void
    {
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, $this->proxy]));
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, 'NotAnObject']));
        $this->assertFalse(ClassNameValidator::validate(TestHandler::class, [$this->class]));
    }
}
