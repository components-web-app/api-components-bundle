<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\MappingLoader;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Validator\MappingLoader\TimestampedLoader;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class TimestampedLoaderTest extends TestCase
{
    public function test_both_timestamp_fields_must_not_be_null_in_the_timestamped_group(): void
    {
        $metadata = new ClassMetadata(DummyTimestamped::class);

        self::assertTrue($this->loader()->loadClassMetadata($metadata));
        self::assertSame(['createdAt' => 'createdAt should not be null', 'modifiedAt' => 'modifiedAt should not be null'], $this->notNullMessages($metadata, 'DummyTimestamped:timestamped'));
    }

    public function test_custom_timestamp_fields_must_not_be_null(): void
    {
        $metadata = new ClassMetadata(DummyCustomTimestamped::class);

        self::assertTrue($this->loader()->loadClassMetadata($metadata));
        self::assertSame(['customCreatedAt' => 'customCreatedAt should not be null', 'customModifiedAt' => 'customModifiedAt should not be null'], $this->notNullMessages($metadata, 'DummyCustomTimestamped:timestamped'));
    }

    public function test_a_class_which_is_not_timestamped_gets_no_constraints(): void
    {
        $metadata = new ClassMetadata(DummyComponent::class);

        self::assertFalse($this->loader()->loadClassMetadata($metadata));
        self::assertSame([], $metadata->getConstrainedProperties());
    }

    /**
     * @return array<string, string>
     */
    private function notNullMessages(ClassMetadata $metadata, string $group): array
    {
        $messages = [];
        foreach ($metadata->getConstrainedProperties() as $property) {
            foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
                foreach ($propertyMetadata->findConstraints($group) as $constraint) {
                    self::assertInstanceOf(NotNull::class, $constraint);
                    $messages[$property] = $constraint->message;
                }
            }
        }

        return $messages;
    }

    private function loader(): TimestampedLoader
    {
        return new TimestampedLoader(new TimestampedAttributeReader($this->createStub(ManagerRegistry::class)));
    }
}
