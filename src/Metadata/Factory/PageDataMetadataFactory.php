<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Exception\PageDataNotFoundException;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataFactory implements PageDataMetadataFactoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $resourceClass): PageDataMetadata
    {
        if (!class_exists($resourceClass)) {
            throw new PageDataNotFoundException(\sprintf('`%s` was not found', $resourceClass));
        }

        $reflection = new \ReflectionClass($resourceClass);
        if (!$reflection->implementsInterface(PageDataInterface::class)) {
            throw new PageDataNotFoundException(\sprintf('Resource class `%s` is not a valid page data resource', $resourceClass));
        }

        $manager = $this->registry->getManagerForClass($resourceClass);
        if (!$manager) {
            throw new PageDataNotFoundException(\sprintf('Cannot find manager for page data resource `%s`', $resourceClass));
        }
        $classMetadata = $manager->getClassMetadata($resourceClass);

        $abstractReflection = new \ReflectionClass(AbstractPageData::class);
        $reflProps = $abstractReflection->getProperties();
        $abstractProps = array_map(static function (\ReflectionProperty $prop) {
            return $prop->name;
        }, $reflProps);

        $assocFields = array_filter($classMetadata->getAssociationNames(), static function ($name) use ($abstractProps, $classMetadata) {
            return !\in_array($name, $abstractProps, true) && !$classMetadata->isCollectionValuedAssociation($name);
        });

        $metadata = new PageDataMetadata($resourceClass);
        foreach ($assocFields as $assocField) {
            $targetClass = $classMetadata->getAssociationTargetClass($assocField);
            $metadataCollection = $this->resourceMetadataFactory->create($targetClass);
            if (0 === \count($metadataCollection)) {
                continue;
            }
            /** @var ApiResource $apiResource */
            $apiResource = $metadataCollection[0];
            $relationName = $apiResource->getShortName();
            $metadata->addProperty(new PageDataPropertyMetadata($assocField, $targetClass, $relationName));
        }

        return $metadata;
    }
}
