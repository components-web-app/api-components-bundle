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
use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Exception\BadMethodCallException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnsupportedAnnotationException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableAnnotationReader extends AnnotationReader implements UploadableAnnotationReaderInterface
{
    private bool $imagineBundleEnabled;

    public function __construct(Reader $reader, ManagerRegistry $managerRegistry, bool $imagineBundleEnabled)
    {
        $this->imagineBundleEnabled = $imagineBundleEnabled;
        parent::__construct($reader, $managerRegistry);
    }

    public function isConfigured($class): bool
    {
        $isConfigured = parent::isConfigured($class);
        if (!$isConfigured || $this->imagineBundleEnabled || !is_a($class, ImagineFiltersInterface::class)) {
            return $isConfigured;
        }
        throw new BadMethodCallException(sprintf('LiipImagineBundle is not enabled/installed so you should not configure Imagine filters on %s', \is_string($class) ? $class : \get_class($class)));
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Uploadable
    {
        return $this->getClassAnnotationConfiguration($class, Uploadable::class);
    }

    public function isFieldConfigured(\ReflectionProperty $property): bool
    {
        try {
            $this->getPropertyConfiguration($property);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    public function getPropertyConfiguration(\ReflectionProperty $property): UploadableField
    {
        /** @var UploadableField|null $annotation */
        if (!$annotation = $this->reader->getPropertyAnnotation($property, UploadableField::class)) {
            throw new InvalidArgumentException(sprintf('%s::%s does not have %s annotation', $property->getDeclaringClass()->getName(), $property->getName(), UploadableField::class));
        }

        if (\count($annotation->imagineFilters) && !$this->imagineBundleEnabled) {
            throw new BadMethodCallException(sprintf('LiipImagineBundle is not enabled/installed so you should not configure Imagine filters on %s::$%s', $property->class, $property->getName()));
        }

        return $annotation;
    }

    /**
     * @param object|string $data
     *
     * @return UploadableField[]
     */
    public function getConfiguredProperties($data, bool $skipUploadableCheck = false): iterable
    {
        if (!$skipUploadableCheck && !$this->isConfigured($data)) {
            throw new UnsupportedAnnotationException(sprintf('Cannot get field configuration for %s: is it not configured as Uploadable', \is_string($data) ? $data : \get_class($data)));
        }

        $found = false;
        $reflectionProperties = (new \ReflectionClass($data))->getProperties();
        foreach ($reflectionProperties as $reflectionProperty) {
            try {
                $config = $this->getPropertyConfiguration($reflectionProperty);
                yield $reflectionProperty->getName() => $config;
                $found = true;
            } catch (InvalidArgumentException $e) {
            }
        }
        if (!$found) {
            throw new UnsupportedAnnotationException(sprintf('No field configurations on your Uploadable component %s.', \is_string($data) ? $data : \get_class($data)));
        }
    }
}
