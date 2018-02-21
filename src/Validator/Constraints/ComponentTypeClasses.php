<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class ComponentTypeClasses
 * @package Silverback\ApiComponentBundle\Validator\Constraints
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class ComponentTypeClasses extends Constraint
{
    public $message = 'The array contains at least one class which is not valid.{{ string }}';
}
