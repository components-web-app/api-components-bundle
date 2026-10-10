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

final class PhpMemoryLimit
{
    private const array UNITS = ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];

    public static function parse(string $value): ?int
    {
        if (!preg_match('/^(\d+)([KMG]?)$/i', trim($value), $matches)) {
            return null;
        }

        $bytes = (int) $matches[1] * self::UNITS[strtoupper($matches[2])];

        return $bytes > 0 ? $bytes : null;
    }

    public function limit(): ?int
    {
        return self::parse((string) \ini_get('memory_limit'));
    }

    public function usage(): int
    {
        return memory_get_usage(true);
    }

    public function raiseTo(int $bytes): ?string
    {
        $current = $this->limit();
        if (null === $current || $current >= $bytes) {
            return null;
        }

        $previous = (string) \ini_get('memory_limit');
        ini_set('memory_limit', (string) $bytes);

        return $previous;
    }

    public function restore(string $previous): bool
    {
        return false !== @ini_set('memory_limit', $previous);
    }
}
