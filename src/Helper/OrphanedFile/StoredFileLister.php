<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

final class StoredFileLister
{
    /**
     * @param list<string> $prefixes
     * @param list<string> $excludedPrefixes
     *
     * @return iterable<FileAttributes>
     */
    public function list(Filesystem $filesystem, array $prefixes, array $excludedPrefixes): iterable
    {
        $excludedPrefixes = array_map(static fn (string $prefix): string => ltrim($prefix, '/'), $excludedPrefixes);
        foreach ($this->outermost($prefixes) as $prefix) {
            if ($this->isExcluded($prefix, $excludedPrefixes)) {
                continue;
            }
            $slash = strrpos($prefix, '/');
            yield from $this->walk($filesystem, false === $slash ? '' : substr($prefix, 0, $slash), $prefix, $excludedPrefixes);
        }
    }

    /**
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private function outermost(array $prefixes): array
    {
        $prefixes = array_unique(array_map(static fn (string $prefix): string => ltrim($prefix, '/'), $prefixes));
        sort($prefixes);
        $outermost = [];
        foreach ($prefixes as $prefix) {
            foreach ($outermost as $outer) {
                if (str_starts_with($prefix, $outer)) {
                    continue 2;
                }
            }
            $outermost[] = $prefix;
        }

        return $outermost;
    }

    /**
     * @param list<string> $excludedPrefixes
     *
     * @return iterable<FileAttributes>
     */
    private function walk(Filesystem $filesystem, string $directory, string $prefix, array $excludedPrefixes): iterable
    {
        /** @var StorageAttributes $item */
        foreach ($filesystem->listContents($directory, false) as $item) {
            $path = $item->path();
            if ($item instanceof FileAttributes) {
                if (str_starts_with($path, $prefix) && !$this->isExcluded($path, $excludedPrefixes)) {
                    yield $item;
                }
                continue;
            }
            $directoryPath = $path . '/';
            if ((str_starts_with($directoryPath, $prefix) || str_starts_with($prefix, $directoryPath)) && !$this->isExcluded($directoryPath, $excludedPrefixes)) {
                yield from $this->walk($filesystem, $path, $prefix, $excludedPrefixes);
            }
        }
    }

    /**
     * @param list<string> $excludedPrefixes
     */
    private function isExcluded(string $path, array $excludedPrefixes): bool
    {
        foreach ($excludedPrefixes as $excludedPrefix) {
            if (str_starts_with($path, $excludedPrefix)) {
                return true;
            }
        }

        return false;
    }
}
