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

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $attributes = $reflectionClass->getAttributes(Publishable::class);
        if (!\count($attributes)) {
            return true;
        }
        /** @var Publishable $configuration */
        $configuration = $attributes[0]->newInstance();

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = \sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);
        $writeGroup = \sprintf('%s:%s:write', $shortClassName, self::GROUP_NAME);

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->fieldName] ?? null))
            && !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
            $attributeMetadata->addGroup($writeGroup);
        }

        if (
            ($attributeMetadata = ($allAttributesMetadata[$configuration->associationName] ?? null))
            && !empty($attributeMetadata->getGroups())
        ) {
            $attributeMetadata->addGroup($readGroup);
        }

        if (
            $attributeMetadata = ($allAttributesMetadata[$configuration->reverseAssociationName] ?? null)
        ) {
            $authorizedReadGroup = \sprintf('%s:%s:read:authorized', $shortClassName, self::GROUP_NAME);
            $attributeMetadata->addGroup($authorizedReadGroup);
        }

        return true;
    }
}
