<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\StoredFile;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFile;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImage;
use Silverback\ApiComponentsBundle\Validator\StoredFile\SkippedConstraintValidator;
use Silverback\ApiComponentsBundle\Validator\StoredFile\StoredFileConstraintValidator;
use Silverback\ApiComponentsBundle\Validator\StoredFile\StoredFileConstraintValidatorFactory;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\SequentiallyValidator;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StoredFileConstraintValidatorFactoryTest extends TestCase
{
    public function test_file_and_image_constraints_share_the_stored_file_validator(): void
    {
        $factory = new StoredFileConstraintValidatorFactory();

        $validator = $factory->getInstance(new File());

        self::assertInstanceOf(StoredFileConstraintValidator::class, $validator);
        self::assertSame($validator, $factory->getInstance(new Image()));
    }

    public function test_the_stored_file_validator_is_given_the_configured_pixel_limit(): void
    {
        $file = new StoredFile($this->createStub(FilesystemOperator::class), 'a.png', ['mimeType' => 'image/png', 'fileSize' => 10, 'width' => 500, 'height' => 715]);

        self::assertCount(1, $this->validator(new StoredFileConstraintValidatorFactory(defaultMaxPixels: 357499))->validate($file, new UploadedImage()));
        self::assertCount(0, $this->validator(new StoredFileConstraintValidatorFactory(defaultMaxPixels: null))->validate($file, new UploadedImage(maxPixels: null)));
        self::assertCount(0, $this->validator(new StoredFileConstraintValidatorFactory())->validate($file, new UploadedImage()));
        self::assertCount(1, $this->validator(new StoredFileConstraintValidatorFactory())->validate($file, new UploadedImage(maxPixels: 357499)));
    }

    public function test_composites_are_validated_by_symfonys_own_validators_by_default(): void
    {
        $factory = new StoredFileConstraintValidatorFactory();

        self::assertInstanceOf(WhenValidator::class, $factory->getInstance(new When(expression: 'true', constraints: [new File()])));
        self::assertInstanceOf(SequentiallyValidator::class, $factory->getInstance(new Sequentially([new File()])));
    }

    public function test_composites_are_validated_by_the_given_factory(): void
    {
        $when = new When(expression: 'true', constraints: [new File()]);
        $whenValidator = new WhenValidator();
        $composites = $this->createMock(ConstraintValidatorFactoryInterface::class);
        $composites->expects(self::once())->method('getInstance')->with($when)->willReturn($whenValidator);

        self::assertSame($whenValidator, (new StoredFileConstraintValidatorFactory($composites))->getInstance($when));
    }

    public function test_every_other_constraint_is_skipped_by_one_shared_validator(): void
    {
        $factory = new StoredFileConstraintValidatorFactory();

        $validator = $factory->getInstance(new NotBlank());

        self::assertInstanceOf(SkippedConstraintValidator::class, $validator);
        self::assertSame($validator, $factory->getInstance(new Regex('/a/')));
    }

    private function validator(StoredFileConstraintValidatorFactory $factory): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->setConstraintValidatorFactory($factory)->getValidator();
    }
}
