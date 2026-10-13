<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache\Warm;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class HttpCacheWarmRequest
{
    public function __construct(
        public string $url,
        public int $status,
        public float $duration,
        public ?bool $hit = null,
        public ?string $error = null,
    ) {
    }

    public function isFailed(): bool
    {
        return null !== $this->error || $this->status < 200 || $this->status >= 300;
    }
}
