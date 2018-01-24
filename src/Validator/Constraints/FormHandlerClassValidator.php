<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FormHandlerClassValidator extends ConstraintValidator
{
    private $formHandlers;

    public function __construct(
        iterable $formHandlers
    )
    {
        $this->formHandlers = $formHandlers;
    }

    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }
        if (!class_exists($value)) {
            $this->context
                ->buildViolation($constraint->message . ' No such class exists')
                ->setParameter('{{ string }}', $value)
                ->addViolation()
            ;
            return;
        }
        $valid = false;
        foreach ($this->formHandlers as $formHandler)
        {
            if (get_class($formHandler) === $value) {
                $valid = true;
                break;
            } else {
                $refl = new \ReflectionClass($formHandler);
                if ($refl->isSubclassOf($value))
                {
                    $valid = true;
                    break;
                }
            }
        }
        if (!$valid) {
            $conditionsStr = vsprintf(' It should implement %s or tagged %s', [
                FormHandlerInterface::class,
                'silverback_api_component.form_handler'
            ]);
            $this->context
                ->buildViolation($constraint->message . $conditionsStr)
                ->setParameter('{{ string }}', $value)
                ->addViolation()
            ;
        }
    }
}
