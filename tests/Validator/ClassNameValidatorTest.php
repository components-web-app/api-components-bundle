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

namespace Silverback\ApiComponentBundle\Tests\Validator;

use PHPUnit\Framework\TestCase;
use ProxyManager\Configuration;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\ProxyInterface;
use ReflectionException;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;

class ClassNameValidatorTest extends TestCase
{
    private FileComponent $class;

    private ProxyInterface $proxy;

    protected function setUp(): void
    {
        $this->class = new FileComponent();
        $factory = new LazyLoadingValueHolderFactory(new Configuration());
        $this->proxy = $factory->createProxy(
            FileComponent::class,
            static function (&$wrappedObject) {
                $wrappedObject = new FileComponent();
            }
        );
    }

    /**
     * @throws ReflectionException
     */
    public function test_validate(): void
    {
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, $this->proxy]));
        $this->assertTrue(ClassNameValidator::validate(FileInterface::class, [$this->class, 'NotAnObject']));
    }

    /**
     * @throws ReflectionException
     */
    public function test_class_same_validation_success(): void
    {
        $this->assertFalse(ClassNameValidator::isClassSame(User::class, $this->class));
        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->class));
        $this->assertTrue(ClassNameValidator::isClassSame(FileComponent::class, $this->proxy));
        $this->assertTrue(ClassNameValidator::isClassSame(FileInterface::class, $this->class));
    }

    /**
     * @throws ReflectionException
     */
    public function test_class_same_validation_invalid_classname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClassNameValidator::isClassSame('NotAClass', $this->class);
    }
}
