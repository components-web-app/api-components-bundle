<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormHandlerClassValidator extends ConstraintValidator
{
    private $formHandlers;

    public function __construct(
        iterable $formHandlers
    ) {
        $this->formHandlers = $formHandlers;
    }

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }
        try {
            $valid = ClassNameValidator::validate($value, $this->formHandlers);
            if (!$valid) {
                $conditionsStr = vsprintf(
                    ' It should implement %s or tagged %s',
                    [
                        FormHandlerInterface::class,
                        'silverback_api_component.form_handler',
                    ]
                );
                $this->context
                    ->buildViolation($constraint->message . $conditionsStr)
                    ->setParameter('{{ string }}', $value)
                    ->addViolation();
            }
        } catch (InvalidArgumentException $exception) {
            $this->context
                ->buildViolation($constraint->message . ' ' . $exception->getMessage())
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}
