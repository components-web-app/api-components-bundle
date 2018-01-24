<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class FormHandlerClass extends Constraint
{
    public $message = 'The string "{{ string }}" does not refer to a class configured correctly as a form handler.';
}
