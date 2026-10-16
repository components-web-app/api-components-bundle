<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class RouteName
{
    public const int MAX_LENGTH = 255;

    public static function fromPath(string $path): string
    {
        return trim(str_replace('/', '-', $path), '-') ?: 'root';
    }

    public static function withSuffix(string $name, int $suffix): string
    {
        $suffix = '-' . $suffix;

        return mb_substr($name, 0, self::MAX_LENGTH - \strlen($suffix)) . $suffix;
    }
}
