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

use PHPUnit\Framework\TestCase;
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

class StoredFileConstraintValidatorFactoryTest extends TestCase
{
    public function test_file_and_image_constraints_share_the_stored_file_validator(): void
    {
        $factory = new StoredFileConstraintValidatorFactory();

        $validator = $factory->getInstance(new File());

        self::assertInstanceOf(StoredFileConstraintValidator::class, $validator);
        self::assertSame($validator, $factory->getInstance(new Image()));
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
}
