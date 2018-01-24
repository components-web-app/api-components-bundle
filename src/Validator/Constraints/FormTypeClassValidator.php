<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\AbstractType;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FormTypeClassValidator extends ConstraintValidator
{
    private $formTypes;

    public function __construct(
        iterable $formTypes
    )
    {
        $this->formTypes = $formTypes;
    }

    public function validate($value, Constraint $constraint)
    {
        $valid = false;
        foreach ($this->formTypes as $formType)
        {
            if (get_class($formType) === $value) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $conditionsStr = vsprintf(' It should extend %s, implement %s or tagged %s', [
                AbstractType::class,
                FormTypeInterface::class,
                'silverback_api_component.form_type'
            ]);
            $this->context
                ->buildViolation($constraint->message . $conditionsStr)
                ->setParameter('{{ string }}', $value)
                ->addViolation()
            ;
        }
    }
}
