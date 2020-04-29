<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Flysystem;

use League\Flysystem\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

class FilesystemProvider
{
    public const FILESYSTEM_ADAPTER_TAG = 'silverback.api_component.filesystem_adapter';
    /**
     * @var array|AbstractAdapter[]
     */
    private array $adapters = [];

    public function addAdapter(string $name, FilesystemAdapter $adapter): void
    {
        $this->adapters[$name] = $adapter;
    }

    public function getAdapter(string $name): ?FilesystemAdapter
    {
        return $this->adapters[$name] ?? null;
    }
}
