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
use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:timestamped serialization group on {CLASS}.createdAt and {CLASS}.updatedAt for Timestamped entities.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class UploadableLoader implements LoaderInterface
{
    public const GROUP_NAME = 'uploadable';

    private AnnotationReader $reader;
    private UploadableAnnotationReader $annotationReader;

    public function __construct(AnnotationReader $reader, UploadableAnnotationReader $annotationReader)
    {
        $this->reader = $reader;
        $this->annotationReader = $annotationReader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        /** @var Uploadable $configuration */
        if (!$configuration = $this->reader->getClassAnnotation($reflectionClass, Uploadable::class)) {
            return true;
        }

        $properties = $reflectionClass->getProperties();

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);
        $writeGroup = sprintf('%s:%s:write', $shortClassName, self::GROUP_NAME);

        foreach ($properties as $property) {
            if (
                $this->annotationReader->isFieldConfigured($property) &&
                ($attributeMetadata = ($allAttributesMetadata[$property->getName()] ?? null)) &&
                empty($attributeMetadata->getGroups())
            ) {
                $attributeMetadata->addGroup($readGroup);
                $attributeMetadata->addGroup($writeGroup);
            }
        }

        return true;
    }
}
