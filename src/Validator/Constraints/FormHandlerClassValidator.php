<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

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
                        'silverback_api_component.form_handler'
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
