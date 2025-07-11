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

namespace Silverback\ApiComponentsBundle\Validator;

use Doctrine\Persistence\Proxy;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ClassNameValidator
{
    /** @throws \ReflectionException */
    public static function validate(string $className, iterable $validClasses): bool
    {
        foreach ($validClasses as $validClass) {
            if (self::isClassSame($className, $validClass)) {
                return true;
            }
        }

        return false;
    }

    /** @throws \ReflectionException */
    public static function isClassSame(string $className, object $validClass): bool
    {
        if (!class_exists($className) && !interface_exists($className)) {
            throw new InvalidArgumentException(\sprintf('The class/interface %s does not exist', $className));
        }

        if ($validClass::class === $className) {
            return true;
        }

        return self::isClassSameLazy($className, $validClass) || $validClass instanceof $className;
    }

    /** @throws \ReflectionException */
    private static function isClassSameLazy(string $className, $validClass): bool
    {
        if (\in_array(LazyObjectInterface::class, class_implements($validClass), true) || \in_array(Proxy::class, class_implements($validClass), true)) {
            $reflection = new \ReflectionClass($validClass);

            return $reflection->isSubclassOf($className);
        }

        return false;
    }
}
