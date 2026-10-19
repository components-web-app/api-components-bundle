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

final class RouteSecurityPattern
{
    public const string LIKE_ESCAPE = '!';

    public static function matches(string $pattern, string $path): bool
    {
        return 1 === preg_match(self::regex($pattern), $path);
    }

    public static function regex(string $pattern): string
    {
        return \sprintf('#\A%s\z#is', str_replace('\*', '.*', preg_quote($pattern, '#')));
    }

    public static function like(string $pattern): string
    {
        return strtr(strtolower($pattern), [
            self::LIKE_ESCAPE => self::LIKE_ESCAPE . self::LIKE_ESCAPE,
            '%' => self::LIKE_ESCAPE . '%',
            '_' => self::LIKE_ESCAPE . '_',
            '*' => '%',
        ]);
    }
}
