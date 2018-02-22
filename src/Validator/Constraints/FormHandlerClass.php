<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class FormHandlerClass
 * @package Silverback\ApiComponentBundle\Validator\Constraints
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class FormHandlerClass extends Constraint
{
    public $message = 'The string "{{ string }}" does not refer to a class configured correctly as a form handler.';
}
