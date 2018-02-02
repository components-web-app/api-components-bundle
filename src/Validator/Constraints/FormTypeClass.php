<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class FormTypeClass extends Constraint
{
    public $message = 'The string "{{ string }}" does not refer to a class configured correctly as a form type.';
}
