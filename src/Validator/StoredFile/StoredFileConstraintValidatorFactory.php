<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\StoredFile;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;

final class StoredFileConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    private ?StoredFileConstraintValidator $storedFileValidator = null;
    private ?SkippedConstraintValidator $skippedValidator = null;

    public function __construct(private readonly ConstraintValidatorFactoryInterface $compositeValidatorFactory = new ConstraintValidatorFactory())
    {
    }

    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        if ($constraint instanceof File) {
            return $this->storedFileValidator ??= new StoredFileConstraintValidator();
        }
        if ($constraint instanceof Composite) {
            return $this->compositeValidatorFactory->getInstance($constraint);
        }

        return $this->skippedValidator ??= new SkippedConstraintValidator();
    }
}
