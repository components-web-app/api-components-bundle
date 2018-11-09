<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\AbstractType;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

class FormTypeClassValidator extends ConstraintValidator
{
    private $formTypes;

    public function __construct(
        iterable $formTypes
    ) {
        $this->formTypes = $formTypes;
    }

    /**
     * @param mixed $value
     * @param Constraint $constraint
     * @throws \ReflectionException
     */
    public function validate($value, Constraint $constraint): void
    {
        try {
            $valid = ClassNameValidator::validate($value, $this->formTypes);
            if (!$valid) {
                $conditionsStr = vsprintf(
                    ' It should extend %s, implement %s or tagged %s',
                    [
                        AbstractType::class,
                        FormTypeInterface::class,
                        'silverback_api_component.form_type'
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
