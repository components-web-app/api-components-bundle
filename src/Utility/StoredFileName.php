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

final class StoredFileName
{
    public static function withToken(string $originalName, ?string $mimeType, string $token): string
    {
        return self::stem($originalName) . '-' . $token . self::extension($originalName, $mimeType);
    }

    public static function withoutToken(string $originalName, ?string $mimeType): string
    {
        return self::stem($originalName) . self::extension($originalName, $mimeType);
    }

    public static function fromStoredPath(string $storedPath, ?string $mimeType): string
    {
        $basename = pathinfo($storedPath, \PATHINFO_BASENAME);
        $extension = pathinfo($basename, \PATHINFO_EXTENSION);

        return self::withoutToken(self::stripToken(pathinfo($basename, \PATHINFO_FILENAME)) . ('' === $extension ? '' : '.' . $extension), $mimeType);
    }

    public static function stripToken(string $stem): string
    {
        return (string) preg_replace('/-[0-9a-f]{8}$/', '', $stem);
    }

    private static function stem(string $originalName): string
    {
        $stem = trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo($originalName, \PATHINFO_FILENAME))), '-');

        return substr('' === $stem ? 'file' : $stem, 0, 100);
    }

    private static function extension(string $originalName, ?string $mimeType): string
    {
        $extension = StoredFileExtension::forMimeType(pathinfo($originalName, \PATHINFO_EXTENSION), $mimeType);

        return null === $extension ? '' : '.' . $extension;
    }
}
