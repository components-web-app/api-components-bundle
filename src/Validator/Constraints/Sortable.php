<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Sortable extends Constraint
{
    public $message = 'THe sort value cannot be null and the value could not be generated automatically';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
