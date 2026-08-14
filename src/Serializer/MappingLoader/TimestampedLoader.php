<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\MappingLoader;

use Silverback\ApiComponentsBundle\Annotation\Timestamped;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:timestamped:read to both timestamp fields of a Timestamped entity, and
 * {CLASS}:timestamped:write to the modified date only — the creation date is never the client's to
 * set. Field names come from the #[Timestamped] attribute and default to createdAt and modifiedAt.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedLoader implements LoaderInterface
{
    public const GROUP_NAME = 'timestamped';

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $attributes = $reflectionClass->getAttributes(Timestamped::class);
        if (!\count($attributes)) {
            return true;
        }
        /** @var Timestamped $configuration */
        $configuration = $attributes[0]->newInstance();

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = \sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);
        $writeGroup = \sprintf('%s:%s:write', $shortClassName, self::GROUP_NAME);
        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->createdAtField] ?? null))
            && empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->modifiedAtField] ?? null))
            && empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
            $attributeMetadata->addGroup($writeGroup);
        }

        return true;
    }
}
