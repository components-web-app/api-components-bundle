<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AssignableRoles extends Constraint
{
    public string $message = 'You may not assign the role(s) {{ roles }}.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
