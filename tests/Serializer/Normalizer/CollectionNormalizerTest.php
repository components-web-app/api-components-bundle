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

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Component\Collection;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\CollectionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class CollectionNormalizerTest extends TestCase
{
    public function test_a_collection_component_is_supported(): void
    {
        self::assertTrue((new CollectionNormalizer(new ResourceMetadataProvider()))->supportsNormalization($this->collection()));
    }

    public function test_anything_other_than_a_collection_component_is_not_supported(): void
    {
        $normalizer = new CollectionNormalizer(new ResourceMetadataProvider());

        self::assertFalse($normalizer->supportsNormalization(['id' => 1]));
        self::assertFalse($normalizer->supportsNormalization(new \ArrayObject(['id' => 1])));
        self::assertFalse($normalizer->supportsNormalization(new \stdClass()));
        self::assertFalse($normalizer->supportsNormalization(new DummyComponent()));
    }

    public function test_a_collection_already_being_normalized_is_not_supported_again(): void
    {
        $normalizer = new CollectionNormalizer(new ResourceMetadataProvider());
        $collection = $this->collection();

        self::assertFalse($normalizer->supportsNormalization($collection, null, ['COLLECTION_NORMALIZER_ALREADY_CALLED' => [$collection->getId()]]));
        self::assertTrue($normalizer->supportsNormalization($collection, null, ['COLLECTION_NORMALIZER_ALREADY_CALLED' => [$this->collection()->getId()]]));
    }

    public function test_normalizing_a_collection_marks_its_metadata_and_is_not_supported_within_its_own_normalization(): void
    {
        $metadataProvider = new ResourceMetadataProvider();
        $normalizer = new CollectionNormalizer($metadataProvider);
        $collection = $this->collection();
        $supportedWithin = null;
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function (object $object, ?string $format, array $context) use ($normalizer, &$supportedWithin): array {
            $supportedWithin = $normalizer->supportsNormalization($object, $format, $context);

            return ['normalized' => true];
        });
        $normalizer->setNormalizer($inner);

        self::assertSame(['normalized' => true], $normalizer->normalize($collection, 'jsonld'));
        self::assertFalse($supportedWithin);
        self::assertTrue($metadataProvider->findResourceMetadata($collection)->getCollection());
        self::assertSame(['object' => false], $normalizer->getSupportedTypes(null));
    }

    private function collection(): Collection
    {
        $collection = new Collection();
        (new \ReflectionProperty($collection, 'id'))->setValue($collection, Uuid::uuid4());

        return $collection;
    }
}
