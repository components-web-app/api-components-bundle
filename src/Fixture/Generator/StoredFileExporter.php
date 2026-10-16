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
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Utility\StoredFileName;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;

/**
 * @internal
 */
final class StoredFileExporter implements StoredFileExport
{
    /** @var array<string, true> */
    private array $names = [];

    public function __construct(
        private readonly FilesystemProvider $filesystemProvider,
        private readonly string $directory,
    ) {
    }

    public function expression(string $adapter, string $storedPath, string $field, ImportCollector $imports): string
    {
        return \sprintf("new %s(__DIR__ . '/assets/%s')", $imports->add(File::class), $this->export($adapter, $storedPath));
    }

    /**
     * @throws FilesystemException
     */
    public function export(string $adapter, string $storedPath): string
    {
        $contents = $this->filesystemProvider->getFilesystem($adapter)->read($storedPath);
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
        $file = $this->directory . '/.export';
        file_put_contents($file, $contents);

        $exported = StoredFileName::fromStoredPath($storedPath, MimeTypes::getDefault()->guessMimeType($file));
        $extension = pathinfo($exported, \PATHINFO_EXTENSION);
        $stem = pathinfo($exported, \PATHINFO_FILENAME);
        $suffix = '' === $extension ? '' : '.' . $extension;
        $name = $exported;
        for ($i = 2; isset($this->names[$name]); ++$i) {
            $name = $stem . '-' . $i . $suffix;
        }
        $this->names[$name] = true;
        rename($file, $this->directory . '/' . $name);

        return $name;
    }

    public function count(): int
    {
        return \count($this->names);
    }
}
