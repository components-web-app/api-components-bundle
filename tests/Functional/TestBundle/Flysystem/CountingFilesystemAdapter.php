<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Flysystem;

use League\Flysystem\CalculateChecksumFromStream;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

final class CountingFilesystemAdapter implements FilesystemAdapter, ChecksumProvider
{
    use CalculateChecksumFromStream;

    /**
     * @var list<array{0: string, 1: string}>
     */
    private static array $calls = [];

    public function __construct(private readonly FilesystemAdapter $inner)
    {
    }

    public static function reset(): void
    {
        self::$calls = [];
    }

    /**
     * @return list<array{0: string, 1: string}> the method and path of each call
     */
    public static function calls(): array
    {
        return self::$calls;
    }

    public static function count(?string $method = null): int
    {
        if (null === $method) {
            return \count(self::$calls);
        }

        return \count(array_filter(self::$calls, static fn (array $call): bool => $call[0] === $method));
    }

    private static function record(string $method, string $path): void
    {
        self::$calls[] = [$method, $path];
    }

    public function fileExists(string $path): bool
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        self::record(__FUNCTION__, $path);
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        self::record(__FUNCTION__, $path);

        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        self::record(__FUNCTION__, $source);
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        self::record(__FUNCTION__, $source);
        $this->inner->copy($source, $destination, $config);
    }

    public function checksum(string $path, Config $config): string
    {
        self::record(__FUNCTION__, $path);

        if ($this->inner instanceof ChecksumProvider) {
            return $this->inner->checksum($path, $config);
        }

        return $this->calculateChecksumFromStream($path, $config);
    }
}
