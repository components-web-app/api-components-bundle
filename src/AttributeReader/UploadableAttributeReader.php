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
final class UploadableAttributeReader extends AttributeReader implements UploadableAttributeReaderInterface
{
    private bool $imagineBundleEnabled;

    public function __construct(ManagerRegistry $managerRegistry, bool $imagineBundleEnabled)
    {
        $this->imagineBundleEnabled = $imagineBundleEnabled;
        parent::__construct($managerRegistry);
    }

    public function isConfigured(object|string $class): bool
    {
        $isConfigured = parent::isConfigured($class);
        if (!$isConfigured || $this->imagineBundleEnabled || !is_a($class, ImagineFiltersInterface::class)) {
            return $isConfigured;
        }
        throw new BadMethodCallException(\sprintf('LiipImagineBundle is not enabled/installed so you should not configure Imagine filters on %s', \is_string($class) ? $class : $class::class));
    }

    public function getConfiguration(object|string $class): Uploadable
    {
        $uploadable = $this->getClassAttributeConfiguration($class, Uploadable::class);
        if (!$uploadable instanceof Uploadable) {
            throw new \LogicException(\sprintf('getClassAnnotationConfiguration should return the type %s', Uploadable::class));
        }

        return $uploadable;
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
        $attributes = $property->getAttributes(UploadableField::class);
        if (!\count($attributes)) {
            throw new InvalidArgumentException(\sprintf('%s::%s does not have %s annotation', $property->getDeclaringClass()->getName(), $property->getName(), UploadableField::class));
        }
        /** @var UploadableField $attribute */
        $attribute = $attributes[0]->newInstance();

        if (!$this->imagineBundleEnabled && null !== $attribute->imagineFilters && \count($attribute->imagineFilters)) {
            throw new BadMethodCallException(\sprintf('LiipImagineBundle is not enabled/installed so you should not configure Imagine filters on %s::$%s', $property->class, $property->getName()));
        }

        return $attribute;
    }

    /**     *
     * @return UploadableField[]|\Generator
     */
    public function getConfiguredProperties(object|string $data, bool $skipUploadableCheck = false): \Generator
    {
        if (!$skipUploadableCheck && !$this->isConfigured($data)) {
            throw new UnsupportedAnnotationException(\sprintf('Cannot get field configuration for %s: is it not configured as Uploadable', \is_string($data) ? $data : $data::class));
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
            throw new UnsupportedAnnotationException(\sprintf('No field configurations on your Uploadable component %s.', \is_string($data) ? $data : $data::class));
        }
    }
}
