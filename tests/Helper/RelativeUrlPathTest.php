<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\RelativeUrlPath;

class RelativeUrlPathTest extends TestCase
{
    public static function acceptedRequestValues(): iterable
    {
        yield 'root' => ['/'];
        yield 'simple path' => ['/reset-password/user/token'];
        yield 'encoded characters' => ['/confirm/new%40example.com/abc'];
        yield 'query and fragment' => ['/path?a=1&b=2#frag'];
        yield 'colon inside the path' => ['/path:with:colons'];
        yield 'unicode' => ['/caf%C3%A9/é'];
        yield 'unreplaced placeholder' => ['/path/{{new_email}}'];
    }

    #[DataProvider('acceptedRequestValues')]
    public function test_a_plain_relative_path_from_a_request_is_accepted(string $value): void
    {
        self::assertSame($value, RelativeUrlPath::fromRequestValue($value)?->path);
    }

    public static function rejectedRequestValues(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute url' => ['https://evil.example/path'];
        yield 'scheme without slashes' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.example/path'];
        yield 'protocol relative with backslash' => ['/\\evil.example/path'];
        yield 'leading backslashes' => ['\\\\evil.example/path'];
        yield 'backslash later in the path' => ['/path\\evil'];
        yield 'no leading slash' => ['relative/path'];
        yield 'double slash later in the path' => ['/path//evil.example'];
        yield 'tab' => ["/\t/evil.example"];
        yield 'newline' => ["/path\nX-Header: 1"];
        yield 'carriage return' => ["/path\r"];
        yield 'null byte' => ["/path\0"];
        yield 'space' => ['/path with space'];
        yield 'delete character' => ["/path\x7F"];
        yield 'unit separator' => ["/path\x1F"];
    }

    #[DataProvider('rejectedRequestValues')]
    public function test_a_request_value_that_is_not_a_plain_relative_path_is_rejected(string $value): void
    {
        self::assertNull(RelativeUrlPath::fromRequestValue($value));
    }

    public function test_a_configured_path_is_given_a_single_leading_slash(): void
    {
        self::assertSame('/login', RelativeUrlPath::fromConfiguration('/login')->path);
        self::assertSame('/login', RelativeUrlPath::fromConfiguration('login')->path);
        self::assertSame('/login', RelativeUrlPath::fromConfiguration('///login')->path);
    }
}
