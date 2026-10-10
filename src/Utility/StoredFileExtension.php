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

use Symfony\Component\Mime\MimeTypes;

final class StoredFileExtension
{
    public static function forMimeType(string $extension, ?string $mimeType): ?string
    {
        $extension = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $extension));
        $extension = '' === $extension ? null : $extension;
        if (null === $mimeType || 'application/octet-stream' === $mimeType) {
            return $extension;
        }

        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
        if (null !== $extension && ('text/plain' === $mimeType || \in_array($extension, $extensions, true))) {
            return $extension;
        }

        return $extensions[0] ?? $extension;
    }

    public static function replaceInPath(string $path, string $mimeType): string
    {
        $separator = strrpos($path, '/');
        $directory = false === $separator ? '' : substr($path, 0, $separator + 1);
        $basename = false === $separator ? $path : substr($path, $separator + 1);
        $current = pathinfo($basename, \PATHINFO_EXTENSION);
        $stem = '' === $current ? $basename : substr($basename, 0, -\strlen($current) - 1);
        $extension = self::forMimeType($current, $mimeType);

        return $directory . $stem . (null === $extension ? '' : '.' . $extension);
    }
}
