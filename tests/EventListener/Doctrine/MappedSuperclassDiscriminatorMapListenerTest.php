<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\MappedSuperclassDiscriminatorMapListener;
use Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine\Fixtures\AbstractEntityChild;
use Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine\Fixtures\ConcreteChild;
use Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine\Fixtures\DiscriminatorRoot;
use Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine\Fixtures\MappedSuperclassChild;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AbstractDummyAppComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class MappedSuperclassDiscriminatorMapListenerTest extends TestCase
{
    private const FULL_MAP = [
        'discriminatorroot' => DiscriminatorRoot::class,
        'mappedsuperclasschild' => MappedSuperclassChild::class,
        'abstractentitychild' => AbstractEntityChild::class,
        'concretechild' => ConcreteChild::class,
    ];

    public function test_a_mapped_superclass_is_removed_from_the_discriminator_map_and_sub_classes(): void
    {
        $metadata = $this->rootMetadata(DiscriminatorRoot::class, self::FULL_MAP);

        (new MappedSuperclassDiscriminatorMapListener([DiscriminatorRoot::class]))->loadClassMetadata($this->eventArgs($metadata));

        self::assertSame(
            [
                'discriminatorroot' => DiscriminatorRoot::class,
                'abstractentitychild' => AbstractEntityChild::class,
                'concretechild' => ConcreteChild::class,
            ],
            $metadata->discriminatorMap
        );
        self::assertSame([AbstractEntityChild::class, ConcreteChild::class], $metadata->subClasses);
    }

    public function test_a_map_without_a_mapped_superclass_is_untouched(): void
    {
        $map = self::FULL_MAP;
        unset($map['mappedsuperclasschild']);
        $metadata = $this->rootMetadata(DiscriminatorRoot::class, $map);

        (new MappedSuperclassDiscriminatorMapListener([DiscriminatorRoot::class]))->loadClassMetadata($this->eventArgs($metadata));

        self::assertSame($map, $metadata->discriminatorMap);
        self::assertSame([AbstractEntityChild::class, ConcreteChild::class], $metadata->subClasses);
    }

    public function test_a_root_that_is_not_listed_is_untouched(): void
    {
        $metadata = $this->rootMetadata(DiscriminatorRoot::class, self::FULL_MAP);

        (new MappedSuperclassDiscriminatorMapListener([AbstractComponent::class]))->loadClassMetadata($this->eventArgs($metadata));

        self::assertSame(self::FULL_MAP, $metadata->discriminatorMap);
        self::assertSame([MappedSuperclassChild::class, AbstractEntityChild::class, ConcreteChild::class], $metadata->subClasses);
    }

    public function test_a_listed_class_that_is_not_the_root_of_its_hierarchy_is_untouched(): void
    {
        $metadata = $this->rootMetadata(DiscriminatorRoot::class, self::FULL_MAP);
        $metadata->rootEntityName = \stdClass::class;

        (new MappedSuperclassDiscriminatorMapListener([DiscriminatorRoot::class]))->loadClassMetadata($this->eventArgs($metadata));

        self::assertSame(self::FULL_MAP, $metadata->discriminatorMap);
    }

    public function test_the_bundle_joined_roots_are_handled_by_default(): void
    {
        $componentMap = [
            'abstractcomponent' => AbstractComponent::class,
            'abstractdummyappcomponent' => AbstractDummyAppComponent::class,
            'dummycomponent' => DummyComponent::class,
        ];
        $component = $this->rootMetadata(AbstractComponent::class, $componentMap);
        $pageData = $this->rootMetadata(AbstractPageData::class, [
            'abstractpagedata' => AbstractPageData::class,
            'mappedsuperclasschild' => MappedSuperclassChild::class,
        ]);

        $listener = new MappedSuperclassDiscriminatorMapListener();
        $listener->loadClassMetadata($this->eventArgs($component));
        $listener->loadClassMetadata($this->eventArgs($pageData));

        self::assertSame(['abstractcomponent' => AbstractComponent::class, 'dummycomponent' => DummyComponent::class], $component->discriminatorMap);
        self::assertSame(['abstractpagedata' => AbstractPageData::class], $pageData->discriminatorMap);
    }

    /**
     * @param array<string, class-string> $map
     */
    private function rootMetadata(string $className, array $map): ClassMetadata
    {
        $metadata = new ClassMetadata($className);
        $metadata->discriminatorMap = $map;
        $metadata->subClasses = array_values(array_filter($map, static fn (string $mapped) => $mapped !== $className));

        return $metadata;
    }

    private function eventArgs(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Fixtures']));

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        return new LoadClassMetadataEventArgs($metadata, $entityManager);
    }
}
