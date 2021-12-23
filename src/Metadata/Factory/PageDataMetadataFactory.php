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

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
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
    private ManagerRegistry $registry;
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;

    public function __construct(
        ManagerRegistry $registry,
        ResourceMetadataFactoryInterface $resourceMetadataFactory
    ) {
        $this->registry = $registry;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function create(string $resourceClass): PageDataMetadata
    {
        // needs to be a class
        if (!class_exists($resourceClass)) {
            throw new PageDataNotFoundException(sprintf('`%s` was not found', $resourceClass));
        }

        // Check it is page data
        $reflection = new \ReflectionClass($resourceClass);
        if (!$reflection->implementsInterface(PageDataInterface::class)) {
            throw new PageDataNotFoundException(sprintf('Resource class `%s` is not a valid page data resource', $resourceClass));
        }

        // Find the doctrine manager
        $manager = $this->registry->getManagerForClass($resourceClass);
        if (!$manager) {
            throw new PageDataNotFoundException(sprintf('Cannot find manager for page data resource `%s`', $resourceClass));
        }
        $classMetadata = $manager->getClassMetadata($resourceClass);

        // Get abstract prop names to exclude
        $abstractReflection = new \ReflectionClass(AbstractPageData::class);
        $reflProps = $abstractReflection->getProperties();
        $abstractProps = array_map(static function (\ReflectionProperty $prop) {
            return $prop->name;
        }, $reflProps);

        // Get all fields with association
        $assocFields = array_filter($classMetadata->getAssociationNames(), static function ($name) use ($abstractProps) {
            return !\in_array($name, $abstractProps, true);
        });

        // Create metadata with relations
        $metadata = new PageDataMetadata($resourceClass);
        foreach ($assocFields as $assocField) {
            $targetClass = $classMetadata->getAssociationTargetClass($assocField);
            $relationName = $this->resourceMetadataFactory->create($targetClass)->getShortName();
            $metadata->addProperty(new PageDataPropertyMetadata($assocField, $relationName));
        }

        return $metadata;
    }
}
