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

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

final readonly class FixtureAssetStore
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private string $prefix,
        private bool $local,
        public string $description,
    ) {
    }

    public function isLocal(): bool
    {
        return $this->local;
    }

    /**
     * @throws FilesystemException
     */
    public function has(string $hash): bool
    {
        return $this->filesystem->fileExists($this->path($hash));
    }

    /**
     * @param resource $stream
     *
     * @throws FilesystemException
     */
    public function write(string $hash, $stream): void
    {
        $this->filesystem->writeStream($this->path($hash), $stream);
    }

    /**
     * @throws FilesystemException
     *
     * @return resource
     */
    public function readStream(string $hash)
    {
        return $this->filesystem->readStream($this->path($hash));
    }

    /**
     * @throws FilesystemException
     *
     * @return list<string>
     */
    public function hashes(): array
    {
        $hashes = [];
        foreach ($this->filesystem->listContents($this->prefix, false) as $item) {
            $name = basename($item->path());
            if ($item->isFile() && 1 === preg_match(FixtureAssetManifest::HASH_PATTERN, $name)) {
                $hashes[] = $name;
            }
        }
        sort($hashes);

        return $hashes;
    }

    /**
     * @param list<string>                  $kept
     * @param (\Closure(string): void)|null $deleted
     *
     * @throws FilesystemException
     *
     * @return list<string>
     */
    public function prune(array $kept, bool $dryRun = false, ?\Closure $deleted = null): array
    {
        $unreferenced = array_values(array_diff($this->hashes(), $kept));
        if (!$dryRun) {
            foreach ($unreferenced as $hash) {
                $this->filesystem->delete($this->path($hash));
                $deleted?->__invoke($hash);
            }
        }

        return $unreferenced;
    }

    private function path(string $hash): string
    {
        return '' === $this->prefix ? $hash : $this->prefix . '/' . $hash;
    }
}
