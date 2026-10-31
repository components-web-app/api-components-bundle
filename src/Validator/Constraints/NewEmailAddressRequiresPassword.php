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
class NewEmailAddressRequiresPassword extends Constraint
{
    public string $message = 'You have not entered your current password correctly. Please try again.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
