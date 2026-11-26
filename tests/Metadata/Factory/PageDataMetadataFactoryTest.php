<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Metadata\Factory;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPageDataSupplier;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPageDataVariant;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithVariants;

final class PageDataMetadataFactoryTest extends TestCase
{
    public function test_a_to_many_association_is_left_out_of_the_metadata(): void
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getAssociationNames')->willReturn(['page', 'component', 'variants']);
        $classMetadata->method('isCollectionValuedAssociation')->willReturnMap([
            ['page', false],
            ['component', false],
            ['variants', true],
        ]);
        $classMetadata->method('getAssociationTargetClass')->willReturnMap([
            ['component', DummyComponent::class],
            ['variants', DummyPageDataVariant::class],
        ]);
        $resourceMetadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')->willReturnCallback(
            static fn (string $class) => new ResourceMetadataCollection($class, [new ApiResource(shortName: (new \ReflectionClass($class))->getShortName())]),
        );

        $metadata = (new PageDataMetadataFactory(self::registryFor($classMetadata), $resourceMetadataFactory))->create(PageDataWithVariants::class);

        self::assertSame(PageDataWithVariants::class, $metadata->getResourceClass());
        self::assertSame([['component', DummyComponent::class, 'DummyComponent']], self::propertiesOf($metadata));
    }

    public function test_an_association_to_a_class_that_is_not_an_api_resource_is_left_out_of_the_metadata(): void
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getAssociationNames')->willReturn(['supplier', 'component']);
        $classMetadata->method('isCollectionValuedAssociation')->willReturn(false);
        $classMetadata->method('getAssociationTargetClass')->willReturnMap([
            ['supplier', DummyPageDataSupplier::class],
            ['component', DummyComponent::class],
        ]);
        $resourceMetadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')->willReturnCallback(
            static fn (string $class) => DummyComponent::class === $class
                ? new ResourceMetadataCollection($class, [new ApiResource(shortName: 'DummyComponent')])
                : new ResourceMetadataCollection($class),
        );

        $metadata = (new PageDataMetadataFactory(self::registryFor($classMetadata), $resourceMetadataFactory))->create(PageDataWithVariants::class);

        self::assertSame([['component', DummyComponent::class, 'DummyComponent']], self::propertiesOf($metadata));
    }

    private static function registryFor(ClassMetadata $classMetadata): ManagerRegistry
    {
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }

    /**
     * @return list<array{string, string, string}>
     */
    private static function propertiesOf(PageDataMetadata $metadata): array
    {
        return array_values(array_map(
            static fn (PageDataPropertyMetadata $property) => [$property->getProperty(), $property->getComponentClass(), $property->getComponentShortName()],
            $metadata->getProperties()->toArray(),
        ));
    }
}
