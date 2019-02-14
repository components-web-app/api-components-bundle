<?php

declare(strict_types=1);

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
    public $message = 'The component `{{ component }}` is not permitted in this location. Permitted classes are `{{ string }}` for the content `{{ content }}`';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
