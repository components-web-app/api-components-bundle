<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Annotation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\InvalidRoutePrefixException;

class RoutePrefixTest extends TestCase
{
    public function test_a_prefix_with_a_leading_slash_and_no_trailing_slash_is_kept(): void
    {
        self::assertSame('/announcements', (new RoutePrefix('/announcements'))->prefix);
    }

    public function test_a_prefix_of_several_segments_is_kept(): void
    {
        self::assertSame('/news/announcements', (new RoutePrefix('/news/announcements'))->prefix);
    }

    public static function invalidPrefixes(): iterable
    {
        yield 'an empty string' => [''];
        yield 'a lone slash' => ['/'];
        yield 'no leading slash' => ['announcements'];
        yield 'a trailing slash' => ['/announcements/'];
        yield 'a trailing slash after several segments' => ['/news/announcements/'];
    }

    #[DataProvider('invalidPrefixes')]
    public function test_an_invalid_prefix_fails_loudly_naming_it(string $prefix): void
    {
        $this->expectException(InvalidRoutePrefixException::class);
        $this->expectExceptionMessage(\sprintf('"%s"', $prefix));

        new RoutePrefix($prefix);
    }

    public function test_an_invalid_prefix_is_not_an_exception_the_attribute_reader_takes_for_a_missing_attribute(): void
    {
        try {
            new RoutePrefix('/');
        } catch (InvalidRoutePrefixException $e) {
            self::assertNotInstanceOf(InvalidArgumentException::class, $e);
            self::assertInstanceOf(\LogicException::class, $e);

            return;
        }

        self::fail('The prefix was accepted.');
    }
}
