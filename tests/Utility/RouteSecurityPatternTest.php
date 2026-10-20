<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Utility;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\RouteSecurityPattern;

class RouteSecurityPatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'a pattern with no wildcard matches its own path' => ['/user-area', '/user-area', true];
        yield 'a pattern with no wildcard does not match a path below it' => ['/user-area', '/user-area/page', false];
        yield 'a pattern with no wildcard does not match a path ending with it' => ['/user-area', '/other/user-area', false];
        yield 'a trailing wildcard matches the path itself' => ['/user-area*', '/user-area', true];
        yield 'a trailing wildcard matches a path below it' => ['/user-area*', '/user-area/page', true];
        yield 'a trailing wildcard matches a path continuing the same segment' => ['/user-area*', '/user-areas', true];
        yield 'a trailing wildcard does not match the pattern further into a path' => ['/user-area*', '/other/user-area/page', false];
        yield 'a trailing wildcard does not match a shorter path' => ['/user-area*', '/user-are', false];
        yield 'a leading wildcard matches the pattern further into a path' => ['*/user-area*', '/other/user-area/page', true];
        yield 'a leading wildcard matches the pattern at the start' => ['*/user-area*', '/user-area', true];
        yield 'a leading wildcard does not match a path without the pattern' => ['*/user-area*', '/user-are/page', false];
        yield 'a wildcard in the middle matches any segments between' => ['/shop/*/basket', '/shop/a/b/basket', true];
        yield 'a wildcard in the middle matches nothing between' => ['/shop/*/basket', '/shop//basket', true];
        yield 'a wildcard in the middle still anchors the end' => ['/shop/*/basket', '/shop/a/basket/x', false];
        yield 'a lone wildcard matches every path' => ['*', '/anything/at/all', true];
        yield 'a dot matches only a dot' => ['/v1.0*', '/v1x0', false];
        yield 'a dot matches a dot' => ['/v1.0*', '/v1.0/page', true];
        yield 'the regular expression delimiter matches itself' => ['/a#b*', '/a#b/page', true];
        yield 'a percent sign matches only a percent sign' => ['/100%*', '/100x/page', false];
        yield 'a percent sign matches a percent sign' => ['/100%*', '/100%/page', true];
        yield 'an underscore matches only an underscore' => ['/my_page', '/myxpage', false];
        yield 'an underscore matches an underscore' => ['/my_page', '/my_page', true];
        yield 'the like escape character matches itself' => ['/hey!*', '/hey!/page', true];
        yield 'the like escape character matches only itself' => ['/hey!_', '/hey!x', false];
        yield 'a path ending in a newline does not match a pattern with no wildcard' => ['/user-area', "/user-area\n", false];
        yield 'a wildcard matches a newline' => ['/user-area*', "/user-area\n/page", true];
        yield 'a question mark matches only a question mark' => ['/what?', '/whatx', false];
        yield 'a question mark matches a question mark' => ['/what?', '/what?', true];
        yield 'brackets match only brackets' => ['/a[bc]', '/ab', false];
        yield 'brackets match brackets' => ['/a[bc]', '/a[bc]', true];
        yield 'a backslash matches only a backslash' => ['/a\b*', '/a\b/page', true];
        yield 'a letter matches only the same letter in the same case' => ['/user-area*', '/User-Area/page', false];
        yield 'an upper case pattern matches only upper case letters' => ['/User-Area*', '/user-area/page', false];
        yield 'an upper case pattern matches the same upper case letters' => ['/User-Area*', '/User-Area/page', true];
        yield 'a non-ASCII letter matches itself' => ['/über*', '/über/x', true];
        yield 'a non-ASCII letter does not match its other case' => ['/Über*', '/über/x', false];
        yield 'a non-ASCII letter does not match its lower case pattern' => ['/über*', '/Über/x', false];
        yield 'a letter does not match the same letter with an accent' => ['/user-area*', '/üser-area/x', false];
        yield 'a letter with an accent does not match the letter without it' => ['/über*', '/uber/x', false];
        yield 'a letter does not match a character whose lower case is that letter' => ['/kelvin*', "/\u{212A}elvin", false];
        yield 'a letter does not match its full width form' => ['/user-area*', "/\u{FF55}ser-area", false];
    }

    #[DataProvider('paths')]
    public function test_a_pattern_matches_the_whole_path_with_an_asterisk_as_its_only_wildcard(string $pattern, string $path, bool $expected): void
    {
        self::assertSame($expected, RouteSecurityPattern::matches($pattern, $path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function likePatterns(): iterable
    {
        yield 'a trailing wildcard' => ['/user-area*', '/user-area%'];
        yield 'a leading and trailing wildcard' => ['*/user-area*', '%/user-area%'];
        yield 'a literal percent sign' => ['/100%*', '/100!%%'];
        yield 'a literal underscore' => ['/my_page', '/my!_page'];
        yield 'the escape character itself' => ['/hey!', '/hey!!'];
        yield 'a backslash' => ['/a\b', '/a\b'];
        yield 'upper case letters' => ['/User-Area', '/User-Area'];
        yield 'non-ASCII letters' => ['/Über*', '/Über%'];
    }

    #[DataProvider('likePatterns')]
    public function test_a_like_pattern_escapes_every_sql_wildcard_and_keeps_every_other_character(string $pattern, string $expected): void
    {
        self::assertSame($expected, RouteSecurityPattern::like($pattern));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function globPatterns(): iterable
    {
        yield 'a trailing wildcard' => ['/user-area*', '/user-area*'];
        yield 'a literal question mark' => ['/what?*', '/what[?]*'];
        yield 'a literal opening bracket' => ['/a[b]*', '/a[[]b]*'];
        yield 'characters that are wildcards in like' => ['/100%_!', '/100%_!'];
        yield 'upper case and non-ASCII letters' => ['/Über', '/Über'];
    }

    #[DataProvider('globPatterns')]
    public function test_a_glob_pattern_escapes_every_glob_wildcard_but_the_asterisk(string $pattern, string $expected): void
    {
        self::assertSame($expected, RouteSecurityPattern::glob($pattern));
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string, string}>
     */
    public static function platforms(): iterable
    {
        yield 'PostgreSQL compares in the C collation' => [new PostgreSQLPlatform(), 'path COLLATE "C" LIKE :p ESCAPE \'!\'', '/100!%%'];
        yield 'MySQL compares bytes' => [new MySQL80Platform(), 'CAST(path AS BINARY) LIKE :p ESCAPE \'!\'', '/100!%%'];
        yield 'MariaDB compares bytes' => [new MariaDBPlatform(), 'CAST(path AS BINARY) LIKE :p ESCAPE \'!\'', '/100!%%'];
        yield 'SQLite uses the case sensitive glob' => [new SQLitePlatform(), 'path GLOB :p', '/100%*'];
    }

    #[DataProvider('platforms')]
    public function test_a_platform_matches_the_column_case_sensitively_with_the_pattern_in_its_own_syntax(AbstractPlatform $platform, string $condition, string $parameter): void
    {
        self::assertSame($condition, RouteSecurityPattern::sqlCondition($platform, 'path', ':p'));
        self::assertSame($parameter, RouteSecurityPattern::sqlParameter($platform, '/100%*'));
    }

    public function test_another_platform_is_refused_rather_than_compared_in_its_collation(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('route_security cannot filter routes on Doctrine\DBAL\Platforms\SQLServerPlatform: only PostgreSQL, MySQL, MariaDB and SQLite are supported.');

        RouteSecurityPattern::sqlCondition(new SQLServerPlatform(), 'path', ':p');
    }
}
