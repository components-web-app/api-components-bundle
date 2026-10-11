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

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Symfony\Component\DependencyInjection\ServiceLocator;

class FilesystemFactoryTest extends TestCase
{
    public function test_a_created_filesystem_is_built_over_its_named_adapter_with_its_config(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        $factory = new FilesystemFactory(new ServiceLocator(['memory' => static fn () => $adapter]));

        $filesystem = $factory->create('memory', ['public_url' => 'https://cdn.example.com']);
        $filesystem->write('file.txt', 'contents');

        self::assertSame($adapter, $factory->getAdapter('memory'));
        self::assertTrue($adapter->fileExists('file.txt'));
        self::assertSame('https://cdn.example.com/file.txt', $filesystem->publicUrl('file.txt'));
    }

    public function test_the_adapter_of_each_filesystem_it_created_is_known(): void
    {
        $memory = new InMemoryFilesystemAdapter();
        $other = new InMemoryFilesystemAdapter();
        $factory = new FilesystemFactory(new ServiceLocator(['memory' => static fn () => $memory, 'other' => static fn () => $other]));

        $memoryFilesystem = $factory->create('memory');
        $otherFilesystem = $factory->create('other');

        self::assertSame($memory, $factory->getAdapterOf($memoryFilesystem));
        self::assertSame($other, $factory->getAdapterOf($otherFilesystem));
    }

    public function test_the_adapter_of_a_filesystem_it_did_not_create_is_unknown(): void
    {
        $factory = new FilesystemFactory(new ServiceLocator([]));

        self::assertNull($factory->getAdapterOf(new Filesystem(new InMemoryFilesystemAdapter())));
    }
}
