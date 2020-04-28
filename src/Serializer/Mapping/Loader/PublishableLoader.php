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

namespace Silverback\ApiComponentBundle\Serializer\Mapping\Loader;

use Doctrine\Common\Annotations\AnnotationReader;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:publishable serialization group on {CLASS}.publishedAt for Publishable entities.
 * Adds {CLASS}:publishable serialization group on {CLASS}.isPublished for Publishable entities with PublishableInterface.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableLoader implements LoaderInterface
{
    private AnnotationReader $reader;

    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        /** @var Publishable $configuration */
        if (!$configuration = $this->reader->getClassAnnotation($reflectionClass, Publishable::class)) {
            return true;
        }

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:publishable:read', $shortClassName);
        $writeGroup = sprintf('%s:publishable:write', $shortClassName);

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
