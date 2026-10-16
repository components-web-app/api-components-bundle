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
use Silverback\ApiComponentsBundle\Utility\StoredFileName;

class StoredFileNameTest extends TestCase
{
    public static function names(): iterable
    {
        yield 'a PHP temp name is lowercased and takes the type\'s extension' => ['phpGPCPOl', 'image/png', 'phpgpcpol.png'];
        yield 'characters outside the slug become one hyphen' => ['My  Photo_(1).PNG', 'image/png', 'my-photo-1.png'];
        yield 'leading and trailing hyphens are trimmed' => ['--hero--.png', 'image/png', 'hero.png'];
        yield 'a name with nothing to slug is file' => ['!!!.png', 'image/png', 'file.png'];
        yield 'a stem is cut to 100 characters' => [str_repeat('a', 120) . '.png', 'image/png', str_repeat('a', 100) . '.png'];
        yield 'an extension valid for the type is kept' => ['photo.jpeg', 'image/jpeg', 'photo.jpeg'];
        yield 'an unknown type with no extension adds none' => ['phpAbC', 'application/octet-stream', 'phpabc'];
        yield 'no type keeps the extension' => ['Report.PDF', null, 'report.pdf'];
    }

    #[DataProvider('names')]
    public function test_the_name_without_its_token_is_the_slug_and_the_extension_for_the_type(string $originalName, ?string $mimeType, string $expected): void
    {
        self::assertSame($expected, StoredFileName::withoutToken($originalName, $mimeType));
    }

    public function test_the_name_with_a_token_puts_the_token_between_the_slug_and_the_extension(): void
    {
        self::assertSame('phpgpcpol-0a1b2c3d.png', StoredFileName::withToken('phpGPCPOl', 'image/png', '0a1b2c3d'));
        self::assertSame('phpabc-0a1b2c3d', StoredFileName::withToken('phpAbC', 'application/octet-stream', '0a1b2c3d'));
    }

    public static function tokenised(): iterable
    {
        yield 'a token is removed' => ['home-hero-0a1b2c3d', 'home-hero'];
        yield 'an upper case token is not a token' => ['home-hero-0A1B2C3D', 'home-hero-0A1B2C3D'];
        yield 'a short hex suffix is not a token' => ['image-2', 'image-2'];
        yield 'a token not at the end is kept' => ['a-0a1b2c3d-b', 'a-0a1b2c3d-b'];
        yield 'a name that is only a token is empty' => ['-0a1b2c3d', ''];
    }

    #[DataProvider('tokenised')]
    public function test_the_token_is_stripped_from_a_stored_stem(string $stem, string $expected): void
    {
        self::assertSame($expected, StoredFileName::stripToken($stem));
    }
}
