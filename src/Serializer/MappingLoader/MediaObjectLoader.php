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

namespace Silverback\ApiComponentBundle\Serializer\MappingLoader;

use Doctrine\Common\Annotations\AnnotationReader;
use Silverback\ApiComponentBundle\Annotation\File;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:timestamped serialization group on {CLASS}.createdAt and {CLASS}.updatedAt for Timestamped entities.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class MediaObjectLoader implements LoaderInterface
{
    public const GROUP_NAME = 'media_object';

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
        /** @var File $configuration */
        if (!$configuration = $this->reader->getClassAnnotation($reflectionClass, File::class)) {
            return true;
        }

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->mediaObjectsProperty] ?? null)) &&
            !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        return true;
    }
}
