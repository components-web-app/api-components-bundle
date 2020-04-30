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
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class FilesystemProvider
{
    public const FILESYSTEM_ADAPTER_TAG = 'silverback.api_component.filesystem_adapter';

    private ServiceLocator $adapters;

    public function __construct(ServiceLocator $adapters)
    {
        $this->adapters = $adapters;
    }

    /**
     * @throws RuntimeException
     */
    public function getAdapter(string $name): FilesystemAdapter
    {
        return $this->adapters->get($name);
    }
}
