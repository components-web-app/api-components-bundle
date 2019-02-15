<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class NewUsername extends Constraint
{
    public $differentMessage = 'Your new username should be different';
    public $uniqueMessage = 'Someone else is already registered with that email address';
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
