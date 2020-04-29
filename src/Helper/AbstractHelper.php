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

namespace Silverback\ApiComponentBundle\Helper;

use Doctrine\Common\Annotations\Reader;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;

abstract class AbstractHelper
{
    use ClassMetadataTrait;

    protected Reader $reader;

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
     * @required
     */
    protected function initReader(Reader $reader): void
    {
        $this->reader = $reader;
    }

    /**
     * @param object|string $class
     *
     * @throws \ReflectionException
     */
    protected function getAnnotationConfiguration($class, string $annotationClass)
    {
        if (null === $class || (\is_string($class) && !class_exists($class))) {
            throw new InvalidArgumentException(sprintf('$class passed to %s must be a valid class FQN or object', __CLASS__));
        }

        $reflection = new \ReflectionClass($class);
        /** @var $annotationClass|null $annotation */
        while (
            !($annotation = $this->reader->getClassAnnotation($reflection, $annotationClass)) &&
            ($reflection = $reflection->getParentClass())
        ) {
            continue;
        }
        if (!$annotation) {
            throw new InvalidArgumentException(sprintf('%s does not have %s annotation', \is_object($class) ? \get_class($class) : $class, $annotationClass));
        }

        return $annotation;
    }
}
