<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Flysystem;

use League\Flysystem\Filesystem;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class FilesystemProvider
{
    public const FILESYSTEM_ADAPTER_TAG = 'silverback.api_components.filesystem_adapter';

    private ServiceLocator $adapters;

    public function __construct(ServiceLocator $adapters)
    {
        $this->adapters = $adapters;
    }

    /**
     * @throws RuntimeException
     */
    public function getFilesystem(string $name): Filesystem
    {
        return new Filesystem($this->adapters->get($name));
    }
}
