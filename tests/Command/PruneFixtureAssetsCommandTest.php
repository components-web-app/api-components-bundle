<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\PruneFixtureAssetsCommand;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

class PruneFixtureAssetsCommandTest extends TestCase
{
    private string $directory;
    private Filesystem $bucket;
    private string $current;
    private string $previous;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/cwa-prune-fixture-assets-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->bucket = new Filesystem(new InMemoryFilesystemAdapter());
        foreach (['current', 'previous', 'stale'] as $content) {
            $this->bucket->write('fixtures/' . hash('sha256', $content), $content);
        }
        $this->bucket->write('fixtures/README.md', 'notes');
        $this->current = $this->manifest('Current', 'current');
        $this->previous = $this->manifest('Previous', 'previous');
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_every_asset_no_kept_manifest_names_is_deleted_and_nothing_else(): void
    {
        $tester = $this->prune(['manifests' => [$this->current, $this->previous]], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'current')));
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'previous')));
        self::assertFalse($this->bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
        self::assertTrue($this->bucket->fileExists('fixtures/README.md'));
        self::assertStringContainsString('Deleted 1 asset(s) that no kept manifest names from the fixture asset store the gcloud filesystem under fixtures.', $tester->getDisplay());
        self::assertStringContainsString(hash('sha256', 'stale'), $tester->getDisplay());
    }

    public function test_the_assets_are_listed_only_when_verbose(): void
    {
        $tester = $this->prune(['manifests' => [$this->current, $this->previous]]);

        self::assertStringNotContainsString(hash('sha256', 'stale'), $tester->getDisplay());
    }

    public function test_only_the_listed_manifests_are_kept(): void
    {
        $this->prune(['manifests' => [$this->current]]);

        self::assertFalse($this->bucket->fileExists('fixtures/' . hash('sha256', 'previous')));
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'current')));
    }

    public function test_a_dry_run_lists_what_would_be_deleted_and_deletes_nothing(): void
    {
        $tester = $this->prune(['manifests' => [$this->current, $this->previous], '--dry-run' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
        self::assertStringContainsString('Would delete 1 asset(s) that no kept manifest names from the fixture asset store the gcloud filesystem under fixtures.', $tester->getDisplay());
        self::assertStringContainsString(hash('sha256', 'stale'), $tester->getDisplay());
    }

    public function test_a_manifest_that_cannot_be_read_deletes_nothing(): void
    {
        $tester = $this->prune(['manifests' => [$this->current, $this->directory . '/Missing.assets.json']]);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('The fixture asset manifest ' . $this->directory . '/Missing.assets.json does not exist.', $tester->getDisplay());
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
    }

    public function test_an_invalid_store_deletes_nothing(): void
    {
        $tester = $this->prune(['manifests' => [$this->current], '--assets-store' => 's3']);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString('The fixture asset store "s3" is not one of "local" or "flysystem".', $tester->getDisplay());
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
    }

    public function test_with_no_configured_store_the_folder_beside_the_first_manifest_is_pruned(): void
    {
        mkdir($this->directory . '/assets');
        file_put_contents($this->directory . '/assets/' . hash('sha256', 'current'), 'current');
        file_put_contents($this->directory . '/assets/' . hash('sha256', 'stale'), 'stale');

        $tester = $this->prune(['manifests' => [$this->current]], OutputInterface::VERBOSITY_NORMAL, new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator([]))));

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists($this->directory . '/assets/' . hash('sha256', 'current'));
        self::assertFileDoesNotExist($this->directory . '/assets/' . hash('sha256', 'stale'));
    }

    public function test_a_directory_for_one_run_is_pruned_instead(): void
    {
        mkdir($this->directory . '/elsewhere');
        file_put_contents($this->directory . '/elsewhere/' . hash('sha256', 'stale'), 'stale');

        $this->prune(['manifests' => [$this->current], '--assets-dir' => $this->directory . '/elsewhere']);

        self::assertFileDoesNotExist($this->directory . '/elsewhere/' . hash('sha256', 'stale'));
        self::assertTrue($this->bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
    }

    private function prune(array $input, int $verbosity = OutputInterface::VERBOSITY_NORMAL, ?FixtureAssetStoreFactory $stores = null): CommandTester
    {
        $stores ??= new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator(['gcloud' => fn () => $this->bucket])), true, 'flysystem', null, 'gcloud', 'fixtures');
        $tester = new CommandTester(new PruneFixtureAssetsCommand($stores));
        $tester->execute($input, ['verbosity' => $verbosity]);

        return $tester;
    }

    private function manifest(string $className, string $content): string
    {
        $manifest = new FixtureAssetManifest();
        $manifest->add(hash('sha256', $content), $content . '.txt', 'text/plain', \strlen($content), 'App\\Entity\\Doc::file');
        $path = FixtureAssetManifest::pathFor($this->directory, $className);
        file_put_contents($path, $manifest->toJson());

        return $path;
    }
}
