<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ComponentTypeClasses extends Constraint
{
    public $message = 'The array contains at least one class which is not valid.{{string}}';
}
