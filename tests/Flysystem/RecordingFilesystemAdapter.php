<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

class RecordingFilesystemAdapter extends InMemoryFilesystemAdapter
{
    /**
     * @var array<string, Config>
     */
    public array $writes = [];

    /**
     * @var array<string, Config>
     */
    public array $copies = [];

    public function write(string $path, string $contents, Config $config): void
    {
        $this->writes[$path] = $config;
        parent::write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->writes[$path] = $config;
        parent::writeStream($path, $contents, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->copies[$destination] = $config;
        parent::copy($source, $destination, $config);
    }
}
