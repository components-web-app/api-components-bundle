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

namespace Silverback\ApiComponentsBundle\AnnotationReader;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AnnotationReader implements AnnotationReaderInterface
{
    use ClassMetadataTrait;

    protected Reader $reader;

    private array $configurationCache = [];

    public function __construct(Reader $reader, ManagerRegistry $managerRegistry)
    {
        $this->reader = $reader;
        $this->initRegistry($managerRegistry);
    }

    abstract public function getConfiguration($class);

    /**
     * @param object|string $class
     */
    public function isConfigured($class): bool
    {
        try {
            $this->getConfiguration($class);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param object|string $class
     *
     * @throws \ReflectionException
     */
    protected function getClassAnnotationConfiguration($class, string $annotationClass): ?object
    {
        if (null === $class || (!\is_object($class) && !\is_string($class)) || (\is_string($class) && !class_exists($class))) {
            throw new InvalidArgumentException(sprintf('$class passed to %s must be a valid class FQN or object.', __CLASS__));
        }

        $className = \is_object($class) ? \get_class($class) : $class;
        if (\array_key_exists($className, $this->configurationCache)) {
            return $this->configurationCache[$className];
        }

        $annotation = $this->findAnnotationConfiguration($class, $annotationClass);

        $this->configurationCache[$className] = $annotation;

        return $annotation;
    }

    /**
     * @param string|object $class
     *
     * @throws \ReflectionException
     */
    private function findAnnotationConfiguration($class, string $annotationClass): ?object
    {
        $reflection = new \ReflectionClass($class);
        $annotation = $this->reader->getClassAnnotation($reflection, $annotationClass);
        if (!$annotation) {
            $annotation = $this->getConfigurationFromParentClasses($reflection, $annotationClass);
            if (!$annotation) {
                $annotation = $this->getConfigurationFromTraits($reflection, $annotationClass);
                if (!$annotation) {
                    throw new InvalidArgumentException(sprintf('%s does not have %s annotation', \is_object($class) ? \get_class($class) : $class, $annotationClass));
                }
            }
        }

        return $annotation;
    }

    private function getConfigurationFromParentClasses(\ReflectionClass $reflection, string $annotationClass): ?object
    {
        $annotation = null;

        $parentReflection = $reflection->getParentClass();
        while (
            $parentReflection &&
            !$annotation = $this->reader->getClassAnnotation($parentReflection, $annotationClass)
        ) {
            $parentReflection = $parentReflection->getParentClass();
        }

        return $annotation;
    }

    private function getConfigurationFromTraits(\ReflectionClass $reflection, string $annotationClass): ?object
    {
        $annotation = null;
        $traits = $reflection->getTraits();
        foreach ($traits as $trait) {
            $annotation = $this->reader->getClassAnnotation($trait, $annotationClass);
            if ($annotation) {
                break;
            }
        }

        return $annotation;
    }
}
