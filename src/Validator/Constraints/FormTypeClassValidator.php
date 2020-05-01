<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use ReflectionException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormTypeClassValidator extends ConstraintValidator
{
    private iterable $formTypes;

    public function __construct(
        iterable $formTypes
    ) {
        $this->formTypes = $formTypes;
    }

    /** @throws ReflectionException */
    public function validate($value, Constraint $constraint): void
    {
        if (!$value) {
            return;
        }
        if (!\is_string($value)) {
            throw new InvalidArgumentException(sprintf('The value passed to %s must be a string', __CLASS__));
        }
        if (!$constraint instanceof FormTypeClass) {
            throw new InvalidArgumentException(sprintf('$constraint parameter must be %s', FormTypeClass::class));
        }

        try {
            $valid = ClassNameValidator::validate($value, $this->formTypes);
            if (!$valid) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->setParameter('{{ string }}', $value)
                    ->addViolation();
            }
        } catch (InvalidArgumentException $exception) {
            $this->context
                ->buildViolation($exception->getMessage())
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}
