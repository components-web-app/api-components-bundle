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

use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Symfony\Component\Serializer\Exception\MappingException;
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
    private PublishableHelper $publishableHelper;

    public function __construct(PublishableHelper $publishableHelper)
    {
        $this->publishableHelper = $publishableHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        if (!$this->publishableHelper->isPublishable($classMetadata->getName())) {
            return true;
        }

        $configuration = $this->publishableHelper->getConfiguration($classMetadata->getName());
        foreach ([$configuration->fieldName, $configuration->associationName] as $field) {
            if (!$attributeMetadata = ($classMetadata->getAttributesMetadata()[$field] ?? null)) {
                throw new MappingException(sprintf('Groups on "%s::%s" cannot be added because the field does not exist.', $classMetadata->getName(), $field));
            }

            $attributeMetadata->addGroup(sprintf('%s:publishable', $classMetadata->getName()));
        }

        if (
            is_a($classMetadata->getName(), PublishableInterface::class, true) &&
            ($attributeMetadata = ($classMetadata->getAttributesMetadata()['isPublished'] ?? null))
        ) {
            $attributeMetadata->addGroup(sprintf('%s:publishable', $classMetadata->getName()));
        }

        return true;
    }
}
