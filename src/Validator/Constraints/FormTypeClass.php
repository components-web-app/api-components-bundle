<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class FormTypeClass
 * @package Silverback\ApiComponentBundle\Validator\Constraints
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class FormTypeClass extends Constraint
{
    public $message = 'The string "{{ string }}" does not refer to a class configured correctly as a form type.';
}
