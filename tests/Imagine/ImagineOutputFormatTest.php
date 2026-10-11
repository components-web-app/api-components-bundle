<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Imagine\ImagineOutputFormat;

class ImagineOutputFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sourcesSavedAsWebp(): iterable
    {
        yield 'avif' => ['image/avif'];
        yield 'heic' => ['image/heic'];
        yield 'heif' => ['image/heif'];
    }

    #[DataProvider('sourcesSavedAsWebp')]
    public function test_a_variant_of_a_source_most_drivers_cannot_encode_is_saved_as_webp(string $sourceMimeType): void
    {
        self::assertSame('webp', ImagineOutputFormat::forFilter(['filters' => []], $sourceMimeType));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function sourcesKeptInTheirFormat(): iterable
    {
        yield 'png' => ['image/png'];
        yield 'jpeg' => ['image/jpeg'];
        yield 'webp' => ['image/webp'];
        yield 'unknown' => [null];
    }

    #[DataProvider('sourcesKeptInTheirFormat')]
    public function test_a_variant_of_any_other_source_keeps_its_format(?string $sourceMimeType): void
    {
        self::assertNull(ImagineOutputFormat::forFilter(['filters' => []], $sourceMimeType));
    }

    public function test_a_configured_format_always_wins(): void
    {
        self::assertSame('jpeg', ImagineOutputFormat::forFilter(['format' => 'jpeg'], 'image/avif'));
        self::assertSame('png', ImagineOutputFormat::forFilter(['format' => 'png'], 'image/jpeg'));
    }

    public function test_an_empty_or_invalid_configured_format_is_ignored(): void
    {
        self::assertSame('webp', ImagineOutputFormat::forFilter(['format' => ''], 'image/avif'));
        self::assertSame('webp', ImagineOutputFormat::forFilter(['format' => null], 'image/avif'));
        self::assertNull(ImagineOutputFormat::forFilter(['format' => ''], 'image/png'));
    }
}
