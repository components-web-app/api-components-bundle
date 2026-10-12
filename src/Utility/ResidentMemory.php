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

final readonly class ResidentMemory
{
    public function __construct(private string $statusFile = '/proc/self/status')
    {
    }

    public function bytes(): int
    {
        $status = @file_get_contents($this->statusFile);
        if (\is_string($status) && preg_match('/^VmRSS:\s+(\d+) kB$/m', $status, $matches)) {
            return (int) $matches[1] * 1024;
        }

        return memory_get_usage(true);
    }
}
