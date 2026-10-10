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
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UploadableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadata;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UploadableNormalizerTest extends TestCase
{
    public function test_normalizing_leaves_the_file_fields_of_the_object_unchanged(): void
    {
        $object = new DummyUploadable();
        $object->setFilename('image-1a2b3c4d.png');
        $file = new File(__FILE__);
        $object->file = $file;

        $this->buildNormalizer(['other' => 'kept'])->normalize($object, 'jsonld');

        self::assertSame('image-1a2b3c4d.png', $object->getFilename());
        self::assertSame($file, $object->file);
    }

    public function test_the_file_field_and_its_stored_path_are_left_out_of_the_output(): void
    {
        $object = new DummyUploadable();
        $object->setFilename('image-1a2b3c4d.png');

        $normalized = $this->buildNormalizer(['file' => null, 'filename' => 'image-1a2b3c4d.png', 'other' => 'kept'])->normalize($object, 'jsonld');

        self::assertSame(['other' => 'kept'], $normalized);
    }

    public function test_an_array_object_output_has_the_file_fields_removed_too(): void
    {
        $object = new DummyUploadable();

        $normalized = $this->buildNormalizer(new \ArrayObject(['file' => null, 'filename' => 'image-1a2b3c4d.png', 'other' => 'kept']))->normalize($object, 'jsonld');

        self::assertInstanceOf(\ArrayObject::class, $normalized);
        self::assertSame(['other' => 'kept'], $normalized->getArrayCopy());
    }

    public function test_a_scalar_output_is_returned_unchanged(): void
    {
        self::assertSame('/dummy_uploadables/1', $this->buildNormalizer('/dummy_uploadables/1')->normalize(new DummyUploadable(), 'jsonld'));
    }

    public function test_media_objects_are_set_on_the_resource_metadata(): void
    {
        $metadata = new ResourceMetadata();
        $metadataProvider = $this->createStub(ResourceMetadataProvider::class);
        $metadataProvider->method('findResourceMetadata')->willReturn($metadata);

        $mediaObjectFactory = $this->createStub(MediaObjectFactory::class);
        $mediaObjectFactory->method('createMediaObjects')->willReturn(new ArrayCollection(['file' => []]));

        $this->buildNormalizer(['media' => 'normalized'], $mediaObjectFactory, $metadataProvider)->normalize(new DummyUploadable(), 'jsonld');

        self::assertSame(['media' => 'normalized'], $metadata->getMediaObjects());
    }

    private function buildNormalizer(array|\ArrayObject|string $innerOutput, ?MediaObjectFactory $mediaObjectFactory = null, ?ResourceMetadataProvider $metadataProvider = null): UploadableNormalizer
    {
        $fieldConfiguration = new UploadableField(adapter: 'local');
        $fieldConfiguration->property = 'filename';

        $attributeReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $attributeReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfiguration]);

        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturn($innerOutput);

        $normalizer = new UploadableNormalizer(
            $mediaObjectFactory ?? $this->createStub(MediaObjectFactory::class),
            $attributeReader,
            $this->createStub(UploadableFileManager::class),
            $metadataProvider ?? $this->createStub(ResourceMetadataProvider::class),
        );
        $normalizer->setNormalizer($inner);

        return $normalizer;
    }
}
