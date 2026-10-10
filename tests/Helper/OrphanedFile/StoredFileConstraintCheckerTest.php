<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileConstraintChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithConstraints;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Validation;

class StoredFileConstraintCheckerTest extends TestCase
{
    public function test_the_constraints_are_the_default_group_constraints_on_the_file_property(): void
    {
        $constraints = $this->checker()->constraints(DummyUploadableWithConstraints::class, 'file');

        self::assertCount(2, $constraints);
        self::assertInstanceOf(File::class, $constraints[0]);
        self::assertSame(['image/png', 'image/svg+xml'], $constraints[0]->mimeTypes);
        self::assertInstanceOf(When::class, $constraints[1]);
    }

    public function test_a_property_with_no_constraints_or_no_metadata_has_none(): void
    {
        self::assertSame([], $this->checker()->constraints(DummyUploadable::class, 'file'));
        self::assertSame([], $this->checker()->constraints(DummyUploadableWithConstraints::class, 'filename'));
        self::assertSame([], $this->checker()->constraints(DummyUploadableWithConstraints::class, 'nothing'));

        $metadataFactory = $this->createStub(MetadataFactoryInterface::class);
        $metadataFactory->method('getMetadataFor')->willReturn($this->createStub(MetadataInterface::class));
        self::assertSame([], (new StoredFileConstraintChecker($metadataFactory))->constraints(DummyUploadable::class, 'file'));
    }

    public function test_constraints_only_in_another_group_are_left_out(): void
    {
        $metadata = new ClassMetadata(DummyUploadable::class);
        $metadata->addPropertyConstraint('file', new File(maxSize: 1, groups: ['DummyUploadable:published']));
        $metadata->addPropertyConstraint('file', new File(maxSize: 2));
        $metadataFactory = $this->createStub(MetadataFactoryInterface::class);
        $metadataFactory->method('getMetadataFor')->willReturn($metadata);

        $constraints = (new StoredFileConstraintChecker($metadataFactory))->constraints(DummyUploadable::class, 'file');

        self::assertCount(1, $constraints);
        self::assertInstanceOf(File::class, $constraints[0]);
        self::assertSame(2, $constraints[0]->maxSize);
    }

    public function test_the_violation_messages_are_returned_in_order_and_a_when_expression_sees_the_stored_files_mime_type(): void
    {
        $checker = $this->checker();
        $constraints = $checker->constraints(DummyUploadableWithConstraints::class, 'file');

        self::assertSame(
            ['The image has too many pixels (357500 pixels). Maximum amount expected is 300000 pixels.'],
            $checker->violations($this->file('image/png', 500, 715), $constraints)
        );
        self::assertSame([], $checker->violations($this->file(StoredFile::SVG_MIME_TYPE, 5000, 7150), $constraints));
        self::assertSame([], $checker->violations($this->file('image/png', 100, 100), $constraints));
        self::assertSame([], $checker->violations($this->file('image/png', 500, 715), []));
    }

    public function test_constraints_other_than_file_and_image_are_skipped_whether_or_not_inside_a_composite(): void
    {
        $constraints = [new NotNull(), new NotBlank(), new Sequentially([new NotBlank(), new Image(maxWidth: 10)])];

        self::assertSame(
            ['The image width is too big (500px). Allowed maximum width is 10px.'],
            $this->checker()->violations($this->file('image/png', 500, 715), $constraints)
        );
    }

    public function test_composite_constraints_are_validated_by_the_given_factory(): void
    {
        $factory = $this->createMock(ConstraintValidatorFactoryInterface::class);
        $factory->expects(self::once())->method('getInstance')->with(self::isInstanceOf(When::class))->willReturn(new WhenValidator());
        $checker = new StoredFileConstraintChecker($this->createStub(MetadataFactoryInterface::class), $factory);

        self::assertSame(
            ['The image width is too big (500px). Allowed maximum width is 10px.'],
            $checker->violations($this->file('image/png', 500, 715), [new When(expression: 'value.getSize() > 1', constraints: [new Image(maxWidth: 10)])])
        );
    }

    private function checker(): StoredFileConstraintChecker
    {
        return new StoredFileConstraintChecker(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }

    private function file(string $mimeType, int $width, int $height): StoredFile
    {
        return new StoredFile($this->createStub(FilesystemOperator::class), 'a.png', ['mimeType' => $mimeType, 'fileSize' => 10, 'width' => $width, 'height' => $height]);
    }
}
