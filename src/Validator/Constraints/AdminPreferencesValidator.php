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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AdminPreferencesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AdminPreferences) {
            throw new UnexpectedTypeException($constraint, AdminPreferences::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            $this->context->buildViolation($constraint->notAnObjectMessage)
                ->setCode(AdminPreferences::NOT_AN_OBJECT_ERROR)
                ->addViolation();

            return;
        }

        if (\strlen(json_encode($value, \JSON_THROW_ON_ERROR)) <= AdminPreferences::MAX_BYTES) {
            return;
        }

        $this->context->buildViolation($constraint->tooLargeMessage)
            ->setParameter('{{ limit }}', (string) AdminPreferences::MAX_BYTES)
            ->setCode(AdminPreferences::TOO_LARGE_ERROR)
            ->addViolation();
    }
}
