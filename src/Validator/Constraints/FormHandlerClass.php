<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class FormHandlerClass extends Constraint
{
    public $message = 'The string "{{ string }}" does not refer to a class configured correctly as a form handler.';
}
