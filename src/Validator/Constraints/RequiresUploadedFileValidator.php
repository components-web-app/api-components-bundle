<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RequiresUploadedFileValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof RequiresUploadedFile) {
            throw new UnexpectedTypeException($constraint, RequiresUploadedFile::class);
        }

        if (!\is_object($value)) {
            return;
        }

        $accessor = PropertyAccess::createPropertyAccessor();

        $file = $accessor->isReadable($value, $constraint->fileProperty)
            ? $accessor->getValue($value, $constraint->fileProperty)
            : null;
        $filename = $accessor->isReadable($value, $constraint->filenameProperty)
            ? $accessor->getValue($value, $constraint->filenameProperty)
            : null;

        // Passes if either a new file is being uploaded or a file is already stored.
        if (null !== $file || (null !== $filename && '' !== $filename)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($constraint->fileProperty)
            ->setParameter('{{ property }}', $constraint->fileProperty)
            ->addViolation();
    }
}
