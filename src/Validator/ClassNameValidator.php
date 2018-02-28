<?php

namespace Silverback\ApiComponentBundle\Validator;

use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ClassNameValidator
{
    /**
     * @param string $className
     * @param $validClass
     * @return bool
     * @throws \ReflectionException
     */
    public static function isClassSame(string $className, $validClass): bool
    {
        self::validateParameters($className, $validClass);
        if (\get_class($validClass) === $className) {
            return true;
        }
        return self::isClassSameLazy($className, $validClass) ?: ($validClass instanceof $className);
    }

    /**
     * @param string $className
     * @param $validClass
     */
    private static function validateParameters(string $className, $validClass): void
    {
        if (!class_exists($className) && !interface_exists($className)) {
            throw new InvalidArgumentException(sprintf('The class/interface %s does not exist', $className));
        }
        if (!\is_object($validClass)) {
            throw new InvalidArgumentException(sprintf('The $validClass parameter %s is not an object', $validClass));
        }
    }

    /**
     * @param string $className
     * @param $validClass
     * @return bool
     * @throws \ReflectionException
     */
    private static function isClassSameLazy(string $className, $validClass): bool
    {
        if (\in_array(LazyLoadingInterface::class, class_implements($validClass), true)) {
            $reflection = new \ReflectionClass($validClass);
            return $reflection->isSubclassOf($className);
        }
        return false;
    }

    /**
     * @param string $className
     * @param iterable $validClasses
     * @return bool
     * @throws \Symfony\Component\Validator\Exception\InvalidArgumentException
     * @throws \ReflectionException
     */
    public static function validate(string $className, iterable $validClasses): bool
    {
        foreach ($validClasses as $validClass) {
            if (self::isClassSame($className, $validClass)) {
                return true;
            }
        }
        return false;
    }
}
