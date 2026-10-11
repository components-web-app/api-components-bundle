<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FilesystemFactory
{
    /**
     * @var \WeakMap<Filesystem, FilesystemAdapter>
     */
    private \WeakMap $filesystemAdapters;

    public function __construct(private readonly ServiceLocator $adapters)
    {
        $this->filesystemAdapters = new \WeakMap();
    }

    /**
     * @throws RuntimeException
     */
    public function create(string $name, array $config = []): Filesystem
    {
        $adapter = $this->getAdapter($name);
        $filesystem = new Filesystem($adapter, $config);
        $this->filesystemAdapters[$filesystem] = $adapter;

        return $filesystem;
    }

    public function getAdapterOf(Filesystem $filesystem): ?FilesystemAdapter
    {
        return $this->filesystemAdapters[$filesystem] ?? null;
    }

    public function getAdapter(string $name)
    {
        return $this->adapters->get($name);
    }
}
