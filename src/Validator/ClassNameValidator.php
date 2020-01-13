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

namespace Silverback\ApiComponentBundle\Validator;

use ProxyManager\Proxy\LazyLoadingInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ClassNameValidator
{
    /** @throws ReflectionException */
    public static function validate(string $className, iterable $validClasses): bool
    {
        foreach ($validClasses as $validClass) {
            if (self::isClassSame($className, $validClass)) {
                return true;
            }
        }

        return false;
    }

    /** @throws ReflectionException */
    public static function isClassSame(string $className, object $validClass): bool
    {
        self::validateParameters($className, $validClass);
        if (\get_class($validClass) === $className) {
            return true;
        }

        return self::isClassSameLazy($className, $validClass) ?: ($validClass instanceof $className);
    }

    private static function validateParameters(string $className, $validClass): void
    {
        if (!class_exists($className) && !interface_exists($className)) {
            throw new InvalidArgumentException(sprintf('The class/interface %s does not exist', $className));
        }
        if (!\is_object($validClass)) {
            throw new InvalidArgumentException(sprintf('The $validClass parameter %s is not an object', $validClass));
        }
    }

    /** @throws ReflectionException */
    private static function isClassSameLazy(string $className, $validClass): bool
    {
        if (\in_array(LazyLoadingInterface::class, class_implements($validClass), true)) {
            $reflection = new ReflectionClass($validClass);

            return $reflection->isSubclassOf($className);
        }

        return false;
    }
}
