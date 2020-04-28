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
use Silverback\ApiComponentBundle\Annotation\Timestamped;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:timestamped serialization group on {CLASS}.createdAt and {CLASS}.updatedAt for Timestamped entities.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedLoader implements LoaderInterface
{
    public const GROUP_NAME = 'timestamped';

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
        /** @var Timestamped $configuration */
        if (!$configuration = $this->reader->getClassAnnotation($reflectionClass, Timestamped::class)) {
            return true;
        }

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->createdAtField] ?? null)) &&
            !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->modifiedAtField] ?? null)) &&
            !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        return true;
    }
}
