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

namespace Silverback\ApiComponentsBundle\AttributeReader;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use JetBrains\PhpStorm\Pure;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AttributeReader implements AttributeReaderInterface
{
    use ClassMetadataTrait;

    protected Reader $reader;

    private array $configurationCache = [];

    public function __construct(Reader $reader, ManagerRegistry $managerRegistry)
    {
        $this->reader = $reader;
        $this->initRegistry($managerRegistry);
    }

    abstract public function getConfiguration(object|string $class);

    public function isConfigured(object|string $class): bool
    {
        try {
            $this->getConfiguration($class);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    private function resolveClassName(object|string|null $class): string
    {
        $error = sprintf('$class passed to %s must be a valid class FQN or object.', __CLASS__);
        if (null === $class) {
            throw new InvalidArgumentException($error . ' It is null.');
        }

        if (\is_string($class)) {
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('%s %s is not a class.', $error, $class));
            }

            return $class;
        }

        return \get_class($class);
    }

    /**
     * @throws \ReflectionException
     */
    protected function getClassAttributeConfiguration(object|string|null $class, string $annotationClass): ?object
    {
        $className = $this->resolveClassName($class);
        if (\array_key_exists($className, $this->configurationCache)) {
            return $this->configurationCache[$className];
        }

        $attributes = $this->findAttributeConfiguration($class, $annotationClass);
        if (!\count($attributes)) {
            return null;
        }

        $attribute = $attributes[0];
        $instance = $attribute->newInstance();
        $this->configurationCache[$className] = $instance;

        return $instance;
    }

    /**
     * @param string|object $class
     *
     * @throws \ReflectionException
     *
     * @return \ReflectionAttribute[]
     */
    private function findAttributeConfiguration($class, string $annotationClass): array
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes($annotationClass);

        if (!\count($attributes)) {
            $attributes = $this->getConfigurationFromParentClasses($reflection, $annotationClass);
            if (!\count($attributes)) {
                $attributes = $this->getConfigurationFromTraits($reflection, $annotationClass);
                if (!\count($attributes)) {
                    throw new InvalidArgumentException(sprintf('%s does not have %s annotation', \is_object($class) ? \get_class($class) : $class, $annotationClass));
                }
            }
        }

        return $attributes;
    }

    /**
     * @return \ReflectionAttribute[]
     */
    #[Pure]
    private function getConfigurationFromParentClasses(\ReflectionClass $reflection, string $annotationClass): array
    {
        $attributes = [];

        $parentReflection = $reflection->getParentClass();
        while (
            $parentReflection &&
            !$attributes = $parentReflection->getAttributes($annotationClass)
        ) {
            $parentReflection = $parentReflection->getParentClass();
        }

        return $attributes;
    }

    /**
     * @return \ReflectionAttribute[]
     */
    #[Pure]
    private function getConfigurationFromTraits(\ReflectionClass $reflection, string $annotationClass): array
    {
        $attributes = [];
        $traits = $reflection->getTraits();
        foreach ($traits as $trait) {
            $attributes = $trait->getAttributes($annotationClass);
            if (\count($attributes)) {
                break;
            }
        }

        return $attributes;
    }
}
