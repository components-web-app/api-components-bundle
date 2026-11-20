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

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class RoutePathStart extends Constraint
{
    public const string PATH_OUTSIDE_START_ERROR = '7c5d0f3e-9a41-4f6b-8b2e-3d1a6c9e0f42';

    protected const ERROR_NAMES = [
        self::PATH_OUTSIDE_START_ERROR => 'PATH_OUTSIDE_START_ERROR',
    ];

    public string $message = 'This path must start with "{{ start }}" and continue after it.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
