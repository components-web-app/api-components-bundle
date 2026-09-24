<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class RelativeUrlPath
{
    private function __construct(public string $path)
    {
    }

    public static function fromRequestValue(string $value): ?self
    {
        if (
            !str_starts_with($value, '/')
            || str_contains($value, '//')
            || str_contains($value, '\\')
            || 1 === preg_match('/[\x00-\x20\x7F]/', $value)
        ) {
            return null;
        }

        return new self($value);
    }

    public static function fromConfiguration(string $value): self
    {
        return new self('/' . ltrim($value, '/'));
    }
}
