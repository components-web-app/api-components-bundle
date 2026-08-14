<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\TimestampedNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[CoversClass(TimestampedNormalizer::class)]
class TimestampedNormalizerTest extends TestCase
{
    private function buildNormalizer(\Closure $denormalize): TimestampedNormalizer
    {
        $classMetadata = new ClassMetadata(TimestampedNormalizerFixture::class);
        $classMetadata->initializeReflection(new RuntimeReflectionService());
        $classMetadata->mapField(['fieldName' => 'createdAt', 'type' => 'datetime_immutable']);
        $classMetadata->mapField(['fieldName' => 'modifiedAt', 'type' => 'datetime']);
        $classMetadata->wakeupReflection(new RuntimeReflectionService());

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $reader = new TimestampedAttributeReader($registry);

        $normalizer = new TimestampedNormalizer($registry, $reader, new TimestampedDataPersister($registry, $reader));

        $inner = $this->createStub(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturnCallback(
            static fn ($data, $type, $format = null, array $context = []) => $denormalize($context)
        );
        $normalizer->setDenormalizer($inner);

        return $normalizer;
    }

    public function test_a_client_supplied_created_at_cannot_replace_the_persisted_one(): void
    {
        $persisted = new TimestampedNormalizerFixture();
        $persisted->createdAt = new \DateTimeImmutable('2001-02-03 04:05:06');
        $persisted->modifiedAt = new \DateTime('2001-02-03 04:05:06');

        // The inner denormalizer writes the request body onto the managed object, as the real
        // serializer does — by the time it returns, the original createdAt is already gone.
        $normalizer = $this->buildNormalizer(static function (array $context) {
            $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
            $object->createdAt = new \DateTimeImmutable('1970-01-01 00:00:00');

            return $object;
        });

        $result = $normalizer->denormalize(
            ['createdAt' => '1970-01-01 00:00:00'],
            TimestampedNormalizerFixture::class,
            null,
            [AbstractNormalizer::OBJECT_TO_POPULATE => $persisted]
        );

        self::assertSame('2001-02-03 04:05:06', $result->createdAt->format('Y-m-d H:i:s'));
        self::assertEqualsWithDelta(time(), $result->modifiedAt->getTimestamp(), 5);
    }

    public function test_a_new_resource_gets_a_fresh_created_at(): void
    {
        $normalizer = $this->buildNormalizer(static function () {
            $object = new TimestampedNormalizerFixture();
            $object->createdAt = new \DateTimeImmutable('1970-01-01 00:00:00');

            return $object;
        });

        $result = $normalizer->denormalize(['createdAt' => '1970-01-01 00:00:00'], TimestampedNormalizerFixture::class);

        self::assertEqualsWithDelta(time(), $result->createdAt->getTimestamp(), 5);
        self::assertEqualsWithDelta(time(), $result->modifiedAt->getTimestamp(), 5);
    }

    public function test_an_object_to_populate_without_a_created_at_is_not_forced_back_to_null(): void
    {
        $persisted = new TimestampedNormalizerFixture();

        $normalizer = $this->buildNormalizer(static function (array $context) {
            $object = $context[AbstractNormalizer::OBJECT_TO_POPULATE];
            $object->createdAt = new \DateTimeImmutable('1970-01-01 00:00:00');

            return $object;
        });

        $result = $normalizer->denormalize(
            ['createdAt' => '1970-01-01 00:00:00'],
            TimestampedNormalizerFixture::class,
            null,
            [AbstractNormalizer::OBJECT_TO_POPULATE => $persisted]
        );

        self::assertSame('1970-01-01 00:00:00', $result->createdAt->format('Y-m-d H:i:s'));
    }

    public function test_an_object_to_populate_of_another_type_is_ignored(): void
    {
        // Denormalizing a collection property (e.g. ComponentGroup.pages) leaves the Doctrine
        // PersistentCollection in OBJECT_TO_POPULATE while $type is still the entity class. Reading
        // a creation date off it asks the attribute reader for a Timestamped configuration the
        // collection does not have, which throws and turns the request into a 500.
        $normalizer = $this->buildNormalizer(static function () {
            $object = new TimestampedNormalizerFixture();
            $object->createdAt = new \DateTimeImmutable('1970-01-01 00:00:00');

            return $object;
        });

        $result = $normalizer->denormalize(
            [],
            TimestampedNormalizerFixture::class,
            null,
            [AbstractNormalizer::OBJECT_TO_POPULATE => new ArrayCollection()]
        );

        self::assertSame('1970-01-01 00:00:00', $result->createdAt->format('Y-m-d H:i:s'));
    }

    public function test_it_supports_a_timestamped_type_once_only(): void
    {
        $normalizer = $this->buildNormalizer(static fn () => new TimestampedNormalizerFixture());

        self::assertTrue($normalizer->supportsDenormalization([], TimestampedNormalizerFixture::class));
        self::assertFalse($normalizer->supportsDenormalization([], PlainNormalizerFixture::class));
        self::assertFalse(
            $normalizer->supportsDenormalization([], TimestampedNormalizerFixture::class, null, [
                'TIMESTAMPED_NORMALIZER_ALREADY_CALLED' => [TimestampedNormalizerFixture::class],
            ])
        );
    }

    public function test_supported_types_are_not_cacheable(): void
    {
        $normalizer = $this->buildNormalizer(static fn () => new TimestampedNormalizerFixture());

        self::assertSame(['object' => false], $normalizer->getSupportedTypes(null));
    }
}

#[Silverback\Timestamped]
class TimestampedNormalizerFixture
{
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTime $modifiedAt = null;
}

class PlainNormalizerFixture
{
}
