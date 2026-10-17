<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Asset;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStore;

class FixtureAssetStoreTest extends TestCase
{
    private const string A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string C = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function test_an_asset_is_stored_under_its_hash_below_the_prefix(): void
    {
        $store = new FixtureAssetStore($this->filesystem, 'fixtures/site', false, 'gcloud');

        self::assertFalse($store->has(self::A));
        $store->write(self::A, $this->stream('image'));

        self::assertTrue($store->has(self::A));
        self::assertSame('image', $this->filesystem->read('fixtures/site/' . self::A));
        self::assertSame('image', stream_get_contents($store->readStream(self::A)));
    }

    public function test_with_no_prefix_an_asset_is_stored_at_the_root(): void
    {
        $store = new FixtureAssetStore($this->filesystem, '', true, '/app/assets');
        $store->write(self::A, $this->stream('image'));

        self::assertSame('image', $this->filesystem->read(self::A));
        self::assertTrue($store->isLocal());
        self::assertSame('/app/assets', $store->description);
    }

    public function test_only_hash_named_files_directly_under_the_prefix_are_assets(): void
    {
        $this->filesystem->write('fixtures/' . self::B, 'b');
        $this->filesystem->write('fixtures/' . self::A, 'a');
        $this->filesystem->write('fixtures/README.md', 'notes');
        $this->filesystem->write('fixtures/' . self::C . '.png', 'c');
        $this->filesystem->write('fixtures/' . strtoupper(self::C), 'c');
        $this->filesystem->write('fixtures/nested/' . self::C, 'c');
        $this->filesystem->write(self::C, 'c');

        self::assertSame([self::A, self::B], (new FixtureAssetStore($this->filesystem, 'fixtures', false, 'gcloud'))->hashes());
        self::assertFalse((new FixtureAssetStore($this->filesystem, 'fixtures', false, 'gcloud'))->isLocal());
    }

    public function test_an_empty_or_missing_store_has_no_assets(): void
    {
        self::assertSame([], (new FixtureAssetStore($this->filesystem, 'fixtures', false, 'gcloud'))->hashes());
    }

    public function test_pruning_deletes_every_asset_that_no_kept_hash_names_and_nothing_else(): void
    {
        $this->filesystem->write('fixtures/' . self::A, 'a');
        $this->filesystem->write('fixtures/' . self::B, 'b');
        $this->filesystem->write('fixtures/' . self::C, 'c');
        $this->filesystem->write('fixtures/README.md', 'notes');
        $store = new FixtureAssetStore($this->filesystem, 'fixtures', false, 'gcloud');

        self::assertSame([self::A, self::C], $store->prune([self::B]));

        self::assertSame([self::B], $store->hashes());
        self::assertTrue($this->filesystem->fileExists('fixtures/README.md'));
    }

    public function test_pruning_dropped_assets_deletes_only_what_the_previous_manifest_listed_and_the_new_one_does_not(): void
    {
        $this->filesystem->write('fixtures/' . self::A, 'a');
        $this->filesystem->write('fixtures/' . self::B, 'b');
        $this->filesystem->write('fixtures/' . self::C, 'c');
        $store = new FixtureAssetStore($this->filesystem, 'fixtures', true, 'local');

        self::assertSame([self::A], $store->pruneDropped([self::B, self::A], [self::B]));

        self::assertSame([self::B, self::C], $store->hashes());
    }

    public function test_pruning_dropped_assets_skips_a_listed_asset_the_store_no_longer_holds(): void
    {
        $this->filesystem->write('fixtures/' . self::B, 'b');
        $store = new FixtureAssetStore($this->filesystem, 'fixtures', true, 'local');

        self::assertSame([], $store->pruneDropped([self::A, self::B], [self::B]));

        self::assertSame([self::B], $store->hashes());
    }

    public function test_pruning_dropped_assets_with_no_previous_manifest_deletes_nothing(): void
    {
        $this->filesystem->write('fixtures/' . self::A, 'a');
        $store = new FixtureAssetStore($this->filesystem, 'fixtures', true, 'local');

        self::assertSame([], $store->pruneDropped([], [self::B]));

        self::assertSame([self::A], $store->hashes());
    }

    public function test_a_dry_run_lists_what_pruning_would_delete_and_deletes_nothing(): void
    {
        $this->filesystem->write('fixtures/' . self::A, 'a');
        $this->filesystem->write('fixtures/' . self::B, 'b');
        $store = new FixtureAssetStore($this->filesystem, 'fixtures', false, 'gcloud');

        self::assertSame([self::A], $store->prune([self::B], true));

        self::assertSame([self::A, self::B], $store->hashes());
    }

    /**
     * @return resource
     */
    private function stream(string $contents)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
