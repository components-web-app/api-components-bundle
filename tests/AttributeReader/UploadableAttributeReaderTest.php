<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\AttributeReader;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Exception\UnsupportedAnnotationException;
use Symfony\Component\HttpFoundation\File\File;

#[\PHPUnit\Framework\Attributes\CoversClass(UploadableAttributeReader::class)]
class UploadableAttributeReaderTest extends TestCase
{
    private function buildReader(): UploadableAttributeReader
    {
        return new UploadableAttributeReader($this->createStub(ManagerRegistry::class), true);
    }

    public function test_two_fields_sharing_a_storage_property_throws(): void
    {
        $reader = $this->buildReader();

        $this->expectException(UnsupportedAnnotationException::class);
        $this->expectExceptionMessage('sharing the storage property "filename"');

        iterator_to_array($reader->getConfiguredProperties(CollidingUploadableFixture::class, true));
    }

    public function test_fields_with_distinct_storage_properties_are_returned(): void
    {
        $reader = $this->buildReader();

        $configured = iterator_to_array($reader->getConfiguredProperties(ValidMultiUploadableFixture::class, true));

        self::assertArrayHasKey('file', $configured);
        self::assertArrayHasKey('preview', $configured);
        self::assertSame('filename', $configured['file']->property);
        self::assertSame('previewFilename', $configured['preview']->property);
    }

    private function buildReaderWithoutImagine(): UploadableAttributeReader
    {
        return new UploadableAttributeReader($this->createStub(ManagerRegistry::class), false);
    }

    public function test_default_skip_check_false_rejects_non_uploadable_class(): void
    {
        $reader = $this->buildReader();

        $this->expectException(UnsupportedAnnotationException::class);
        $this->expectExceptionMessage('is it not configured as Uploadable');

        iterator_to_array($reader->getConfiguredProperties(PlainNonUploadableFixture::class));
    }

    public function test_uploadable_class_without_fields_throws_no_field_configurations(): void
    {
        $reader = $this->buildReader();

        $this->expectException(UnsupportedAnnotationException::class);
        $this->expectExceptionMessage('No field configurations');

        iterator_to_array($reader->getConfiguredProperties(EmptyUploadableFixture::class, true));
    }

    public function test_imagine_filters_without_bundle_throws(): void
    {
        $reader = $this->buildReaderWithoutImagine();
        $property = new \ReflectionProperty(ImagineFilterUploadableFixture::class, 'file');

        $this->expectException(\Silverback\ApiComponentsBundle\Exception\BadMethodCallException::class);
        $reader->getPropertyConfiguration($property);
    }

    public function test_field_without_imagine_filters_is_allowed_when_bundle_disabled(): void
    {
        $reader = $this->buildReaderWithoutImagine();
        $property = new \ReflectionProperty(ValidMultiUploadableFixture::class, 'file');

        $config = $reader->getPropertyConfiguration($property);

        self::assertSame('filename', $config->property);
    }
}

class PlainNonUploadableFixture
{
    public ?File $file = null;
}

#[Uploadable]
class EmptyUploadableFixture
{
    public ?string $name = null;
}

#[Uploadable]
class ImagineFilterUploadableFixture
{
    #[UploadableField(adapter: 'local', imagineFilters: ['thumbnail'])]
    public ?File $file = null;
}

#[Uploadable]
class CollidingUploadableFixture
{
    #[UploadableField(adapter: 'local')]
    public ?File $file = null;

    #[UploadableField(adapter: 'local')]
    public ?File $preview = null;
}

#[Uploadable]
class ValidMultiUploadableFixture
{
    public ?string $previewFilename = null;

    #[UploadableField(adapter: 'local')]
    public ?File $file = null;

    #[UploadableField(adapter: 'local', property: 'previewFilename')]
    public ?File $preview = null;
}
