<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Asset;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Container\NotFoundExceptionInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;

final readonly class FixtureAssetStoreFactory
{
    public const string LOCAL = 'local';
    public const string FLYSYSTEM = 'flysystem';

    public function __construct(
        private FilesystemProvider $filesystemProvider,
        private bool $enabled = false,
        private string $store = self::LOCAL,
        private ?string $path = null,
        private ?string $adapter = null,
        private string $prefix = '',
    ) {
    }

    public function forGeneration(string $scaffoldDirectory, ?string $store = null, ?string $directory = null): ?FixtureAssetStore
    {
        if (!$this->enabled && null === $store && null === $directory) {
            return null;
        }
        $store ??= null === $directory ? $this->store : self::LOCAL;
        if (null !== $directory && self::LOCAL !== $store) {
            throw new \InvalidArgumentException('A directory can only be given for the local fixture asset store.');
        }

        return $this->create($store, $directory ?? $this->path ?? $scaffoldDirectory . '/assets');
    }

    public function forLoading(string $scaffoldDirectory): FixtureAssetStore
    {
        return $this->create($this->store, $this->path ?? $scaffoldDirectory . '/assets');
    }

    private function create(string $store, string $localPath): FixtureAssetStore
    {
        if (self::LOCAL === $store) {
            return new FixtureAssetStore(new Filesystem(new LocalFilesystemAdapter($localPath, lazyRootCreation: true)), '', true, $localPath);
        }
        if (self::FLYSYSTEM !== $store) {
            throw new \InvalidArgumentException(\sprintf('The fixture asset store "%s" is not one of "%s" or "%s".', $store, self::LOCAL, self::FLYSYSTEM));
        }
        if (null === $this->adapter || '' === $this->adapter) {
            throw new \InvalidArgumentException('The flysystem fixture asset store needs an adapter.');
        }
        try {
            $filesystem = $this->filesystemProvider->getFilesystem($this->adapter);
        } catch (NotFoundExceptionInterface) {
            throw new \InvalidArgumentException(\sprintf('The fixture asset store adapter "%s" is not a filesystem adapter of this application.', $this->adapter));
        }
        $prefix = trim($this->prefix, '/');
        $description = \sprintf('the %s filesystem', $this->adapter) . ('' === $prefix ? '' : ' under ' . $prefix);

        return new FixtureAssetStore($filesystem, $prefix, false, $description);
    }
}
