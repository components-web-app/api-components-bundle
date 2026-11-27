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

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AdminPreferences extends Constraint
{
    public const int MAX_BYTES = 4096;

    public const string NOT_AN_OBJECT_ERROR = '6b1f0c4e-2a7d-4f39-8e5b-91c3d7a2f064';
    public const string TOO_LARGE_ERROR = 'd84e2b71-5c0a-4a63-b9f2-3e7c1a6d8f25';

    protected const ERROR_NAMES = [
        self::NOT_AN_OBJECT_ERROR => 'NOT_AN_OBJECT_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
    ];

    public string $notAnObjectMessage = 'Admin preferences must be an object or null.';
    public string $tooLargeMessage = 'Admin preferences cannot be over {{ limit }} bytes of JSON.';
}
