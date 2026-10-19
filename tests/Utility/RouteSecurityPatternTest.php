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
        yield 'a backslash matches only a backslash' => ['/a\b*', '/a\b/page', true];
        yield 'letters match regardless of case' => ['/user-area*', '/User-Area/page', true];
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
        yield 'upper case letters' => ['/User-Area', '/user-area'];
    }

    #[DataProvider('likePatterns')]
    public function test_a_like_pattern_escapes_every_sql_wildcard_and_lowers_the_case(string $pattern, string $expected): void
    {
        self::assertSame($expected, RouteSecurityPattern::like($pattern));
    }
}
