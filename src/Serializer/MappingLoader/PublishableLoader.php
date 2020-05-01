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

namespace Silverback\ApiComponentsBundle\Serializer\MappingLoader;

use Doctrine\Common\Annotations\AnnotationReader;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:publishable serialization group on {CLASS}.publishedAt and {CLASS}.publishedResource for Publishable entities.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableLoader implements LoaderInterface
{
    public const GROUP_NAME = 'published';

    private AnnotationReader $reader;

    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        /** @var Publishable $configuration */
        if (!$configuration = $this->reader->getClassAnnotation($reflectionClass, Publishable::class)) {
            return true;
        }

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);
        $writeGroup = sprintf('%s:%s:write', $shortClassName, self::GROUP_NAME);

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->fieldName] ?? null)) &&
            !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
            $attributeMetadata->addGroup($writeGroup);
        }

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->associationName] ?? null)) &&
            !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        return true;
    }
}
