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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:component serialization groups on component entities. This will allow cascaded persists by default and every property accessible by read/write unless a serialization group has been specifically defined on a property.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class CwaResourceLoader implements LoaderInterface
{
    public const GROUP_NAME = 'cwa_resource';

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        if (AbstractComponent::class !== $reflectionClass->getName() && !$reflectionClass->isSubclassOf(AbstractComponent::class) && !$reflectionClass->isSubclassOf(AbstractPageData::class)) {
            return true;
        }

        $allAttributesMetadata = $classMetadata->getAttributesMetadata();
        $shortClassName = $reflectionClass->getShortName();
        $readGroup = sprintf('%s:%s:read', $shortClassName, self::GROUP_NAME);
        $writeGroup = sprintf('%s:%s:write', $shortClassName, self::GROUP_NAME);

        foreach ($allAttributesMetadata as $attributeMetadatum) {
            if ('id' === $attributeMetadatum->getName()) {
                continue;
            }
            if (empty($attributeMetadatum->getGroups())) {
                $attributeMetadatum->addGroup($readGroup);
                $attributeMetadatum->addGroup($writeGroup);
            }
        }

        return true;
    }
}
