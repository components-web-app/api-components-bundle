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
use Silverback\ApiComponentsBundle\Utility\RouteName;

class RouteNameTest extends TestCase
{
    public static function names(): iterable
    {
        yield 'a short name keeps all of itself' => ['about', 1, 'about-1'];
        yield 'a name that fits with its suffix is not shortened' => [str_repeat('a', 253), 1, str_repeat('a', 253) . '-1'];
        yield 'a name filling the column is shortened by the suffix length' => [str_repeat('a', 255), 1, str_repeat('a', 253) . '-1'];
        yield 'a longer suffix shortens the name further' => [str_repeat('a', 255), 10, str_repeat('a', 252) . '-10'];
        yield 'a name one character too long for its suffix loses its last character' => [str_repeat('a', 253) . 'b', 1, str_repeat('a', 253) . '-1'];
        yield 'multibyte characters count as one each' => [str_repeat('é', 255), 1, str_repeat('é', 253) . '-1'];
    }

    #[DataProvider('names')]
    public function test_a_suffixed_name_fits_the_name_column(string $name, int $suffix, string $expected): void
    {
        self::assertSame($expected, RouteName::withSuffix($name, $suffix));
    }

    public static function paths(): iterable
    {
        yield 'the root path' => ['/', 'root'];
        yield 'an empty path' => ['', 'root'];
        yield 'a top-level path' => ['/about', 'about'];
        yield 'a nested path joins its segments with hyphens' => ['/about/team', 'about-team'];
        yield 'a trailing separator is dropped' => ['/about/', 'about'];
    }

    #[DataProvider('paths')]
    public function test_a_name_derived_from_a_path_is_its_segments_joined_by_hyphens(string $path, string $expected): void
    {
        self::assertSame($expected, RouteName::fromPath($path));
    }

    public function test_the_maximum_length_is_the_name_column_length(): void
    {
        self::assertSame(255, RouteName::MAX_LENGTH);
    }
}
