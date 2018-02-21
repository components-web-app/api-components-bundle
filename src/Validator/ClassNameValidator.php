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
        if (\get_class($validClass) === $className) {
            return true;
        }
        if (\in_array(LazyLoadingInterface::class, class_implements($validClass), true)) {
            $refl = new \ReflectionClass($validClass);
            if ($refl->isSubclassOf($className)) {
                return true;
            }
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
        if (!class_exists($className)) {
            throw new InvalidArgumentException(sprintf('The class %s does not exist', $className));
        }
        foreach ($validClasses as $validClass) {
            if (self::isClassSame($className, $validClass)) {
                return true;
            }
        }
        return false;
    }
}
