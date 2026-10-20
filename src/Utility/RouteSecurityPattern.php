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

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class RouteSecurityPattern
{
    public const string LIKE_ESCAPE = '!';

    public static function matches(string $pattern, string $path): bool
    {
        return 1 === preg_match(self::regex($pattern), $path);
    }

    public static function regex(string $pattern): string
    {
        return \sprintf('#\A%s\z#s', str_replace('\*', '.*', preg_quote($pattern, '#')));
    }

    public static function like(string $pattern): string
    {
        return strtr($pattern, [
            self::LIKE_ESCAPE => self::LIKE_ESCAPE . self::LIKE_ESCAPE,
            '%' => self::LIKE_ESCAPE . '%',
            '_' => self::LIKE_ESCAPE . '_',
            '*' => '%',
        ]);
    }

    public static function glob(string $pattern): string
    {
        return strtr($pattern, [
            '[' => '[[]',
            '?' => '[?]',
        ]);
    }

    public static function sqlCondition(AbstractPlatform $platform, string $column, string $placeholder): string
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => \sprintf('%s COLLATE "C" LIKE %s ESCAPE \'%s\'', $column, $placeholder, self::LIKE_ESCAPE),
            $platform instanceof AbstractMySQLPlatform => \sprintf('CAST(%s AS BINARY) LIKE %s ESCAPE \'%s\'', $column, $placeholder, self::LIKE_ESCAPE),
            $platform instanceof SQLitePlatform => \sprintf('%s GLOB %s', $column, $placeholder),
            default => throw new \LogicException(\sprintf('route_security cannot filter routes on %s: only PostgreSQL, MySQL, MariaDB and SQLite are supported.', $platform::class)),
        };
    }

    public static function sqlParameter(AbstractPlatform $platform, string $pattern): string
    {
        return $platform instanceof SQLitePlatform ? self::glob($pattern) : self::like($pattern);
    }
}
