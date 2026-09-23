<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Parameter;

final class BooleanQueryValue
{
    public static function cast(mixed $value): mixed
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!\is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true' => '1',
            'false' => '0',
            default => $value,
        };
    }
}
