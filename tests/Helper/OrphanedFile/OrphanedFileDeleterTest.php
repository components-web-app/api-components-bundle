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
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use Psr\Log\AbstractLogger;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDeleter;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileLister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class OrphanedFileDeleterTest extends OrphanedResourceDatabaseTestCase
{
    private Filesystem $local;
    private Filesystem $publicUrlLocal;
    /** @var list<string> */
    private array $failingPaths = [];
    private OrphanedFileReportStore $store;
    private AbstractLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $adapter = static fn () => new class($test) extends InMemoryFilesystemAdapter {
            public function __construct(private readonly OrphanedFileDeleterTest $test)
            {
                parent::__construct();
            }

            public function delete(string $path): void
            {
                if ($this->test->deleteFails($path)) {
                    throw UnableToDeleteFile::atLocation($path, 'the filestore refused');
                }
                parent::delete($path);
            }
        };
        $this->local = new Filesystem($adapter());
        $this->publicUrlLocal = new Filesystem($adapter());
        $this->store = new OrphanedFileReportStore($this->registry);
        $this->logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }

    public function deleteFails(string $path): bool
    {
        return \in_array($path, $this->failingPaths, true);
    }

    public function test_deleting_selected_paths_deletes_only_those_on_every_filesystem_the_fresh_scan_reports_them(): void
    {
        $this->old($this->local, 'a.png');
        $this->old($this->publicUrlLocal, 'a.png');
        $this->old($this->local, 'b.png');

        $result = $this->deleter()->delete(['a.png']);

        self::assertSame([['adapter' => 'local', 'path' => 'a.png'], ['adapter' => 'public_url_local', 'path' => 'a.png']], $result->deleted);
        self::assertSame([], $result->rejected);
        self::assertFalse($this->local->fileExists('a.png'));
        self::assertFalse($this->publicUrlLocal->fileExists('a.png'));
        self::assertTrue($this->local->fileExists('b.png'));
    }

    public function test_deleting_all_deletes_everything_the_fresh_scan_reports_and_nothing_else(): void
    {
        $this->old($this->local, 'a.png');
        $this->old($this->publicUrlLocal, 'b.png');
        $this->local->write('new.png', 'new');
        $this->referenced('used.png');
        $this->old($this->local, 'used.png');

        $result = $this->deleter()->delete(null);

        self::assertSame([['adapter' => 'local', 'path' => 'a.png'], ['adapter' => 'public_url_local', 'path' => 'b.png']], $result->deleted);
        self::assertSame([], $result->rejected);
        self::assertTrue($this->local->fileExists('new.png'));
        self::assertTrue($this->local->fileExists('used.png'));
    }

    public function test_a_path_the_fresh_scan_does_not_report_is_rejected_and_kept(): void
    {
        $this->old($this->local, 'a.png');
        $this->referenced('used.png');
        $this->old($this->local, 'used.png');
        $this->local->write('new.png', 'new');
        $this->referenced('missing.png');

        $result = $this->deleter()->delete(['used.png', 'new.png', 'missing.png', 'never-existed.png', 'used.png']);

        self::assertSame([], $result->deleted);
        self::assertSame([
            ['path' => 'used.png', 'reason' => OrphanedFileDeleter::NOT_ORPHANED],
            ['path' => 'new.png', 'reason' => OrphanedFileDeleter::NOT_ORPHANED],
            ['path' => 'missing.png', 'reason' => OrphanedFileDeleter::NOT_FOUND],
            ['path' => 'never-existed.png', 'reason' => OrphanedFileDeleter::NOT_FOUND],
        ], $result->rejected);
        self::assertTrue($this->local->fileExists('used.png'));
        self::assertTrue($this->local->fileExists('new.png'));
        self::assertTrue($this->local->fileExists('a.png'));
    }

    public function test_a_path_that_exists_only_on_the_second_filesystem_is_found(): void
    {
        $this->publicUrlLocal->write('new.png', 'new');

        self::assertSame([['path' => 'new.png', 'reason' => OrphanedFileDeleter::NOT_ORPHANED]], $this->deleter()->delete(['new.png'])->rejected);
    }

    public function test_a_failed_delete_is_rejected_and_logged_and_the_others_still_proceed(): void
    {
        $this->old($this->local, 'a.png');
        $this->old($this->local, 'b.png');
        $this->old($this->local, 'c.png');
        $this->failingPaths = ['b.png'];

        $result = $this->deleter()->delete(null);

        self::assertSame([['adapter' => 'local', 'path' => 'a.png'], ['adapter' => 'local', 'path' => 'c.png']], $result->deleted);
        self::assertSame([['path' => 'b.png', 'reason' => OrphanedFileDeleter::DELETE_FAILED]], $result->rejected);
        self::assertTrue($this->local->fileExists('b.png'));
        self::assertCount(1, $this->logger->records);
        [$level, , $context] = $this->logger->records[0];
        self::assertSame('error', $level);
        self::assertSame('b.png', $context['path']);
        self::assertSame('local', $context['adapter']);
        self::assertInstanceOf(UnableToDeleteFile::class, $context['exception']);
    }

    public function test_a_failed_delete_without_a_logger_is_still_rejected(): void
    {
        $this->old($this->local, 'b.png');
        $this->failingPaths = ['b.png'];

        self::assertSame([['path' => 'b.png', 'reason' => OrphanedFileDeleter::DELETE_FAILED]], $this->deleter(false)->delete(null)->rejected);
    }

    public function test_a_deleted_file_takes_its_file_info_rows_with_it(): void
    {
        $this->old($this->local, 'a.png');
        $this->entityManager->persist(new FileInfo('a.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('b.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->flush();

        $this->deleter()->delete(['a.png']);

        self::assertSame(['b.png'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT path FROM ' . $this->entityManager->getClassMetadata(FileInfo::class)->getTableName()));
    }

    public function test_the_stored_report_is_the_fresh_scan_without_what_was_deleted(): void
    {
        $this->old($this->local, 'a.png');
        $this->old($this->local, 'b.png');
        $this->old($this->local, 'c.png');
        $this->referenced('missing.png');
        $this->failingPaths = ['c.png'];
        $this->store->save(new OrphanedFileReport(new \DateTimeImmutable('2020-01-01'), [['adapter' => 'local', 'path' => 'stale.png']]));
        $before = new \DateTimeImmutable();

        $this->deleter()->delete(['a.png', 'c.png']);
        $this->entityManager->clear();

        $report = $this->store->fetch();
        self::assertNotNull($report);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $report->generatedAt->getTimestamp());
        self::assertSame([['adapter' => 'local', 'path' => 'b.png'], ['adapter' => 'local', 'path' => 'c.png']], $report->orphanedFiles);
        self::assertSame('missing.png', $report->missingFiles[0]['path']);
    }

    public function test_nothing_selected_still_stores_the_fresh_scan(): void
    {
        $this->old($this->local, 'a.png');

        $result = $this->deleter()->delete([]);

        self::assertSame([], $result->deleted);
        self::assertSame([['adapter' => 'local', 'path' => 'a.png']], $this->store->fetch()?->orphanedFiles);
    }

    private function old(Filesystem $filesystem, string $path): void
    {
        $filesystem->write($path, $path, [Config::OPTION_VISIBILITY => 'public', 'timestamp' => time() - 7200]);
    }

    private function referenced(string $path): void
    {
        $uploadable = new DummyUploadable();
        $uploadable->setFilename($path);
        $this->persist($uploadable);
        $this->entityManager->flush();
    }

    private function deleter(bool $withLogger = true): OrphanedFileDeleter
    {
        $filesystemProvider = new FilesystemProvider(new ServiceLocator([
            'local' => fn () => $this->local,
            'public_url_local' => fn () => $this->publicUrlLocal,
        ]));
        $reader = new UploadableAttributeReader($this->registry, true);
        $fileManager = new UploadableFileManager(
            $this->registry,
            $reader,
            $filesystemProvider,
            $this->createStub(FlysystemDataLoader::class),
            new FileInfoCacheManager($this->entityManager, new FileInfoRepository($this->registry)),
            null,
            null
        );
        $detector = new OrphanedFileDetector($this->registry, $reader, $filesystemProvider, $this->iriConverter, new StoredFileLister(), [], [], 3600);

        return new OrphanedFileDeleter($detector, $fileManager, $filesystemProvider, $this->store, $withLogger ? $this->logger : null);
    }
}
