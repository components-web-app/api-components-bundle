<?php

namespace Silverback\ApiComponentBundle\Validator;

use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class ClassNameValidator
{
    public static function isClassSame(string $className, $validClass)
    {
        if (get_class($validClass) === $className) {
            return true;
        } elseif (in_array(LazyLoadingInterface::class, class_implements($validClass))) {
            $refl = new \ReflectionClass($validClass);
            if ($refl->isSubclassOf($className)) {
                return true;
            }
        }
        return false;
    }

    public static function validate(string $className, iterable $validClasses)
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
