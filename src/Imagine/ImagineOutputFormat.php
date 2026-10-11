<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Imagine;

final class ImagineOutputFormat
{
    public const string WEBP = 'webp';

    private const array SOURCES_SAVED_AS_WEBP = ['image/avif', 'image/heic', 'image/heif'];

    public static function forFilter(array $filterConfig, ?string $sourceMimeType): ?string
    {
        $format = $filterConfig['format'] ?? null;
        if (\is_string($format) && '' !== $format) {
            return $format;
        }

        return \in_array($sourceMimeType, self::SOURCES_SAVED_AS_WEBP, true) ? self::WEBP : null;
    }
}
