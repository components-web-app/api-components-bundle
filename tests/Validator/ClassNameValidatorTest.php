<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Validator\ClassNameValidator;
use Symfony\Component\VarExporter\LazyObjectInterface;

class ClassNameValidatorTest extends TestCase
{
    private DummyComponent $class;

    //    private LazyObjectInterface $proxy;

    protected function setUp(): void
    {
        $this->class = new DummyComponent();
        //        $factory = new LazyLoadingValueHolderFactory(new Configuration());
        //        $this->proxy = $factory->createProxy(
        //            DummyComponent::class,
        //            static function (&$wrappedObject) {
        //                $wrappedObject = new DummyComponent();
        //            }
        //        );
    }

    /**
     * @throws \ReflectionException
     */
    public function test_validate(): void
    {
        //        $this->assertTrue(ClassNameValidator::validate(ComponentInterface::class, [$this->class, $this->proxy]));
        $this->assertTrue(ClassNameValidator::validate(ComponentInterface::class, [$this->class, 'NotAnObject']));
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_success(): void
    {
        $this->assertFalse(ClassNameValidator::isClassSame(User::class, $this->class));
        $this->assertTrue(ClassNameValidator::isClassSame(DummyComponent::class, $this->class));
        //        $this->assertTrue(ClassNameValidator::isClassSame(DummyComponent::class, $this->proxy));
        $this->assertTrue(ClassNameValidator::isClassSame(ComponentInterface::class, $this->class));
    }

    /**
     * @throws \ReflectionException
     */
    public function test_class_same_validation_invalid_classname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClassNameValidator::isClassSame('NotAClass', $this->class);
    }
}
