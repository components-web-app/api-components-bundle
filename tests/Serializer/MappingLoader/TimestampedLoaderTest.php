<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\MappingLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\TimestampedLoader;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[CoversClass(TimestampedLoader::class)]
class TimestampedLoaderTest extends TestCase
{
    private function loadGroups(string $class, string ...$attributes): array
    {
        $classMetadata = new ClassMetadata($class);
        foreach ($attributes as $attribute) {
            $classMetadata->addAttributeMetadata(new AttributeMetadata($attribute));
        }

        self::assertTrue((new TimestampedLoader())->loadClassMetadata($classMetadata));

        $groups = [];
        foreach ($classMetadata->getAttributesMetadata() as $name => $attributeMetadata) {
            $groups[$name] = $attributeMetadata->getGroups();
        }

        return $groups;
    }

    public function test_created_at_is_readable_but_never_writable(): void
    {
        $groups = $this->loadGroups(TimestampedLoaderFixture::class, 'createdAt', 'modifiedAt');

        // createdAt must not carry the write group: a resource declaring its own serialization
        // groups would otherwise advertise the creation date as a writable input field.
        self::assertSame(['TimestampedLoaderFixture:timestamped:read'], $groups['createdAt']);
        self::assertSame(
            ['TimestampedLoaderFixture:timestamped:read', 'TimestampedLoaderFixture:timestamped:write'],
            $groups['modifiedAt']
        );
    }

    public function test_custom_field_names_are_used(): void
    {
        $groups = $this->loadGroups(CustomTimestampedLoaderFixture::class, 'customCreatedAt', 'customModifiedAt', 'other');

        self::assertSame(['CustomTimestampedLoaderFixture:timestamped:read'], $groups['customCreatedAt']);
        self::assertSame(
            ['CustomTimestampedLoaderFixture:timestamped:read', 'CustomTimestampedLoaderFixture:timestamped:write'],
            $groups['customModifiedAt']
        );
        self::assertSame([], $groups['other']);
    }

    public function test_a_class_without_the_attribute_is_left_alone(): void
    {
        $groups = $this->loadGroups(PlainLoaderFixture::class, 'createdAt', 'modifiedAt');

        self::assertSame([], $groups['createdAt']);
        self::assertSame([], $groups['modifiedAt']);
    }

    public function test_attributes_that_already_declare_groups_are_left_alone(): void
    {
        $classMetadata = new ClassMetadata(TimestampedLoaderFixture::class);
        $classMetadata->addAttributeMetadata($createdAt = new AttributeMetadata('createdAt'));
        $createdAt->addGroup('existing:read');
        $classMetadata->addAttributeMetadata($modifiedAt = new AttributeMetadata('modifiedAt'));
        $modifiedAt->addGroup('existing:read');

        (new TimestampedLoader())->loadClassMetadata($classMetadata);

        self::assertSame(['existing:read'], $createdAt->getGroups());
        self::assertSame(['existing:read'], $modifiedAt->getGroups());
    }

    public function test_missing_attribute_metadata_is_tolerated(): void
    {
        $groups = $this->loadGroups(TimestampedLoaderFixture::class);

        self::assertSame([], $groups);
    }
}

#[Silverback\Timestamped]
class TimestampedLoaderFixture
{
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTime $modifiedAt = null;
}

#[Silverback\Timestamped(createdAtField: 'customCreatedAt', modifiedAtField: 'customModifiedAt')]
class CustomTimestampedLoaderFixture
{
    public ?\DateTimeImmutable $customCreatedAt = null;
    public ?\DateTime $customModifiedAt = null;
    public ?string $other = null;
}

class PlainLoaderFixture
{
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTime $modifiedAt = null;
}
