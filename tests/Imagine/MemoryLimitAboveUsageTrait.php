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

trait MemoryLimitAboveUsageTrait
{
    private static function megabytesAboveUsage(int $megabytes): string
    {
        return \sprintf('%dM', intdiv(memory_get_usage(true), 1048576) + $megabytes);
    }

    private static function bytesOf(string $limit): int
    {
        return (int) substr($limit, 0, -1) * 1048576;
    }

    private static function setMemoryLimit(string $limit): void
    {
        if (false === ini_set('memory_limit', $limit) || $limit !== \ini_get('memory_limit')) {
            throw new \RuntimeException(\sprintf('The memory limit could not be set to %s.', $limit));
        }
    }
}
