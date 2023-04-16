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
 */
class FilesystemFactory
{
    public function __construct(private readonly ServiceLocator $adapters)
    {
    }

    /**
     * @throws RuntimeException
     */
    public function create(string $name, array $config = []): Filesystem
    {
        return new Filesystem($this->getAdapter($name), $config);
    }

    public function getAdapter(string $name) {
        return $this->adapters->get($name);
    }
}
