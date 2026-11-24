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
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class RoutePathCharacters extends Constraint
{
    public const string FORBIDDEN_CHARACTER_ERROR = '3f8b2a6e-1c4d-4e7a-9b5f-6d2e8a0c4b17';

    protected const ERROR_NAMES = [
        self::FORBIDDEN_CHARACTER_ERROR => 'FORBIDDEN_CHARACTER_ERROR',
    ];

    public string $message = 'A path cannot contain whitespace, "?" or "#".';
}
