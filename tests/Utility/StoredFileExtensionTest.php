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
use Silverback\ApiComponentsBundle\Utility\StoredFileExtension;

class StoredFileExtensionTest extends TestCase
{
    public static function extensions(): iterable
    {
        yield 'an extension valid for the type is kept' => ['jpeg', 'image/jpeg', 'jpeg'];
        yield 'an extension wrong for the type is replaced' => ['jpg', 'image/png', 'png'];
        yield 'an empty extension takes the type\'s first' => ['', 'image/webp', 'webp'];
        yield 'an extension is lowercased before it is compared' => ['PNG', 'image/png', 'png'];
        yield 'characters outside an extension are dropped' => ['p-n_g', 'image/png', 'png'];
        yield 'plain text keeps any extension' => ['md', 'text/plain', 'md'];
        yield 'plain text with no extension is txt' => ['', 'text/plain', 'txt'];
        yield 'an unknown type keeps the extension' => ['dat', 'application/octet-stream', 'dat'];
        yield 'an unknown type adds none' => ['', 'application/octet-stream', null];
        yield 'no type keeps the extension' => ['png', null, 'png'];
        yield 'a type with no known extension keeps the extension' => ['abc', 'application/x-made-up', 'abc'];
        yield 'a type with no known extension adds none' => ['', 'application/x-made-up', null];
    }

    #[DataProvider('extensions')]
    public function test_the_extension_for_a_mime_type(string $extension, ?string $mimeType, ?string $expected): void
    {
        self::assertSame($expected, StoredFileExtension::forMimeType($extension, $mimeType));
    }

    public static function paths(): iterable
    {
        yield 'a file in the root' => ['photo.png', 'image/webp', 'photo.webp'];
        yield 'a file in a directory' => ['a/b/photo-1a2b3c4d.png', 'image/webp', 'a/b/photo-1a2b3c4d.webp'];
        yield 'a dot in a directory is not an extension' => ['uploads.v2/scan', 'image/webp', 'uploads.v2/scan.webp'];
        yield 'only the last extension is replaced' => ['archive.tar.png', 'image/webp', 'archive.tar.webp'];
        yield 'a valid extension is kept' => ['photo.jpeg', 'image/jpeg', 'photo.jpeg'];
    }

    #[DataProvider('paths')]
    public function test_the_extension_is_replaced_in_a_path(string $path, string $mimeType, string $expected): void
    {
        self::assertSame($expected, StoredFileExtension::replaceInPath($path, $mimeType));
    }
}
