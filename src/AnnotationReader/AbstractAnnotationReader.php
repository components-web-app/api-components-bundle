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
use Silverback\ApiComponentsBundle\Annotation\Timestamped;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractAnnotationReader
{
    use ClassMetadataTrait;

    protected Reader $reader;

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
    protected function getClassAnnotationConfiguration($class, string $annotationClass)
    {
        if (null === $class || (\is_string($class) && !class_exists($class))) {
            throw new InvalidArgumentException(sprintf('$class passed to %s must be a valid class FQN or object', __CLASS__));
        }

        $originalReflection = $reflection = new \ReflectionClass($class);
        /** @var $annotationClass|null $annotation */
        while (
            !($annotation = $this->reader->getClassAnnotation($reflection, $annotationClass)) &&
            ($reflection = $reflection->getParentClass())
        ) {
            continue;
        }
        if (!$annotation && Timestamped::class === $annotationClass) {
            $traits = $originalReflection->getTraits();
            foreach ($traits as $trait) {
                $annotation = $this->reader->getClassAnnotation($trait, $annotationClass);
                if ($annotation) {
                    break;
                }
            }
        }

        if (!$annotation) {
            throw new InvalidArgumentException(sprintf('%s does not have %s annotation', \is_object($class) ? \get_class($class) : $class, $annotationClass));
        }

        return $annotation;
    }
}
