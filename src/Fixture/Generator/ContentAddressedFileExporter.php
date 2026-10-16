<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator;

use League\Flysystem\FilesystemException;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStore;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Utility\StoredFileName;
use Symfony\Component\Mime\MimeTypes;

/**
 * @internal
 */
final readonly class ContentAddressedFileExporter implements StoredFileExport
{
    public function __construct(
        private FilesystemProvider $filesystemProvider,
        private FixtureAssetStore $store,
        private FixtureAssetManifest $manifest,
        private PhpValueExporter $exporter,
    ) {
    }

    public function expression(string $adapter, string $storedPath, string $field, ImportCollector $imports): string
    {
        $source = $this->filesystemProvider->getFilesystem($adapter)->readStream($storedPath);
        $copy = tmpfile();
        if (false === $copy) {
            throw new \RuntimeException('A temporary file could not be created to export a stored file.');
        }
        try {
            stream_copy_to_stream($source, $copy);
            $path = stream_get_meta_data($copy)['uri'];
            $hash = (string) hash_file('sha256', $path);
            $mimeType = MimeTypes::getDefault()->guessMimeType($path) ?? 'application/octet-stream';
            $size = (int) fstat($copy)['size'];
            $this->store($hash, $copy, $storedPath);
        } finally {
            fclose($copy);
            if (\is_resource($source)) {
                fclose($source);
            }
        }

        $name = StoredFileName::fromStoredPath($storedPath, $mimeType);
        if ($this->manifest->add($hash, $name, $mimeType, $size, $field)) {
            return \sprintf('$cwa->asset(%s)', $this->exporter->export($hash, $imports));
        }

        return \sprintf('$cwa->asset(%s, name: %s)', $this->exporter->export($hash, $imports), $this->exporter->export($name, $imports));
    }

    public function count(): int
    {
        return $this->manifest->count();
    }

    /**
     * @param resource $copy
     */
    private function store(string $hash, $copy, string $storedPath): void
    {
        try {
            if (!$this->store->has($hash)) {
                $this->store->write($hash, $copy);
            }
        } catch (FilesystemException $exception) {
            throw new \RuntimeException(\sprintf('The stored file "%s" could not be written to the fixture asset store (%s) as %s: %s', $storedPath, $this->store->description, $hash, $exception->getMessage()), previous: $exception);
        }
    }
}
