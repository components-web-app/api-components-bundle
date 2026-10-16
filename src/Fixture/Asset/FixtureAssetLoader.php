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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Service\ResetInterface;

final class FixtureAssetLoader implements ResetInterface
{
    /** @var array<string, array{FixtureAssetManifest, FixtureAssetStore}> */
    private array $scaffolds = [];

    /** @var list<resource> */
    private array $copies = [];

    public function __construct(private readonly FixtureAssetStoreFactory $stores)
    {
    }

    public function load(string $manifestPath, string $hash, ?string $name = null): UploadedFile
    {
        [$manifest, $store] = $this->scaffolds[$manifestPath] ??= [FixtureAssetManifest::fromFile($manifestPath), $this->stores->forLoading(\dirname($manifestPath))];
        $entry = $manifest->get($hash);
        if (null === $entry) {
            throw new \RuntimeException(\sprintf('The fixture asset %s is not in the manifest %s. Generate the scaffold and its manifest again.', $hash, $manifestPath));
        }

        $copy = tmpfile();
        if (false === $copy) {
            throw new \RuntimeException('A temporary file could not be created to load a fixture asset.');
        }
        $this->copies[] = $copy;
        try {
            $source = $store->readStream($hash);
        } catch (FilesystemException $exception) {
            throw new \RuntimeException(\sprintf('The fixture asset %s (%s) could not be read from the fixture asset store %s: %s', $hash, $entry['name'], $store->description, $exception->getMessage()), 0, $exception);
        }
        stream_copy_to_stream($source, $copy);
        fclose($source);
        $path = stream_get_meta_data($copy)['uri'];
        if (hash_file('sha256', $path) !== $hash) {
            throw new \RuntimeException(\sprintf('The fixture asset %s (%s) in the fixture asset store %s does not match its hash.', $hash, $entry['name'], $store->description));
        }

        return new UploadedFile($path, $name ?? $entry['name'], $entry['mimeType'], null, true);
    }

    public function reset(): void
    {
        foreach ($this->copies as $copy) {
            fclose($copy);
        }
        $this->copies = [];
        $this->scaffolds = [];
    }
}
