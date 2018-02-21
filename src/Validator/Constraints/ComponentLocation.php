<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Validator\Constraints
 * @author Daniel West <daniel@silverback.is>
 * @Annotation()
 */
class ComponentLocation extends Constraint
{
    public $message = 'The component is not permitted in this location. Permitted classes are {{ string }}';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
