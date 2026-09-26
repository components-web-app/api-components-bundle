<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileLister;

class StoredFileListerTest extends TestCase
{
    public function test_every_file_under_an_empty_prefix_is_listed_at_any_depth(): void
    {
        $filesystem = $this->filesystem(['a.png', 'nested/b.png', 'nested/deeper/c.png']);

        self::assertSame(['a.png', 'nested/b.png', 'nested/deeper/c.png'], $this->paths($filesystem, [''], []));
    }

    public function test_only_files_under_a_directory_prefix_are_listed(): void
    {
        $filesystem = $this->filesystem(['a.png', 'components/b.png', 'components/sub/c.png', 'other/d.png']);

        self::assertSame(['components/b.png', 'components/sub/c.png'], $this->paths($filesystem, ['components/'], []));
    }

    public function test_a_prefix_that_is_part_of_a_filename_lists_only_the_files_it_starts(): void
    {
        $filesystem = $this->filesystem(['img_a.png', 'img_dir/b.png', 'other.png', 'media/img_c.png', 'media/img_x/d.png', 'media/other.png']);

        self::assertSame(['img_a.png', 'img_dir/b.png'], $this->paths($filesystem, ['img_'], []));
        self::assertSame(['media/img_c.png', 'media/img_x/d.png'], $this->paths($filesystem, ['media/img_'], []));
    }

    public function test_a_directory_is_entered_only_when_it_can_hold_files_under_the_prefix(): void
    {
        $filesystem = $this->filesystem(['a/b/c/file.png', 'a/x/file.png', 'a/file.png']);

        self::assertSame(['a/b/c/file.png'], $this->paths($filesystem, ['a/b/'], []));
        self::assertSame(['a/b/c/file.png'], $this->paths($filesystem, ['a/b/c'], []));
    }

    public function test_excluded_paths_are_never_listed(): void
    {
        $filesystem = $this->filesystem(['a.png', 'media/cache/thumbnail/a.png', 'media/b.png', 'exports/report.csv', 'exports-old.csv', 'keep/exports/c.png']);

        self::assertSame(['a.png', 'keep/exports/c.png', 'media/b.png'], $this->paths($filesystem, [''], ['media/cache/', 'exports']));
    }

    public function test_an_excluded_directory_is_not_listed_at_all(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            /** @var list<string> */
            public array $listed = [];

            public function listContents(string $path, bool $deep): iterable
            {
                $this->listed[] = $path;

                return parent::listContents($path, $deep);
            }
        };
        $filesystem = new Filesystem($adapter);
        $filesystem->write('a.png', 'a');
        $filesystem->write('cache/thumbnail/a.png', 'a');

        self::assertSame(['a.png'], $this->paths($filesystem, [''], ['cache/']));
        self::assertSame([''], $adapter->listed);
    }

    public function test_nothing_is_listed_when_the_prefix_itself_is_excluded(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter {
            public int $listings = 0;

            public function listContents(string $path, bool $deep): iterable
            {
                ++$this->listings;

                return parent::listContents($path, $deep);
            }
        };
        $filesystem = new Filesystem($adapter);
        $filesystem->write('cache/a.png', 'a');

        self::assertSame([], $this->paths($filesystem, ['cache/'], ['cache/']));
        self::assertSame([], $this->paths($filesystem, [''], ['']));
        self::assertSame(0, $adapter->listings);
    }

    public function test_a_prefix_inside_another_is_listed_once(): void
    {
        $filesystem = $this->filesystem(['a.png', 'components/b.png']);

        self::assertSame(['a.png', 'components/b.png'], $this->paths($filesystem, ['components/', '', 'components/'], []));
    }

    public function test_leading_slashes_on_prefixes_and_exclusions_are_ignored(): void
    {
        $filesystem = $this->filesystem(['components/b.png', 'components/cache/c.png']);

        self::assertSame(['components/b.png'], $this->paths($filesystem, ['/components/'], ['/components/cache/']));
    }

    public function test_a_missing_prefix_directory_lists_nothing(): void
    {
        self::assertSame([], $this->paths($this->filesystem(['a.png']), ['components/'], []));
    }

    public function test_files_are_listed_with_their_attributes(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('a.png', 'a', [Config::OPTION_VISIBILITY => 'public', 'timestamp' => 1000]);

        $files = iterator_to_array((new StoredFileLister())->list($filesystem, [''], []), false);

        self::assertCount(1, $files);
        self::assertInstanceOf(FileAttributes::class, $files[0]);
        self::assertSame(1000, $files[0]->lastModified());
    }

    /**
     * @param list<string> $paths
     */
    private function filesystem(array $paths): Filesystem
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        foreach ($paths as $path) {
            $filesystem->write($path, $path);
        }

        return $filesystem;
    }

    /**
     * @param list<string> $prefixes
     * @param list<string> $excluded
     *
     * @return list<string>
     */
    private function paths(Filesystem $filesystem, array $prefixes, array $excluded): array
    {
        $paths = array_map(static fn (FileAttributes $file): string => $file->path(), iterator_to_array((new StoredFileLister())->list($filesystem, $prefixes, $excluded), false));
        sort($paths);

        return $paths;
    }
}
