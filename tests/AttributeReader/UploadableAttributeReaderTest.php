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

        // Iterating the generator triggers the collision guard.
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
