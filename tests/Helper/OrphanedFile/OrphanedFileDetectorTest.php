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
use League\Flysystem\UnableToRetrieveMetadata;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileLister;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileNameMatcher;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyMultipleUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadablePublicUrl;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class OrphanedFileDetectorTest extends OrphanedResourceDatabaseTestCase
{
    private const int OLD = 7200;

    private Filesystem $local;
    private Filesystem $publicUrlLocal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->local = new Filesystem(new InMemoryFilesystemAdapter());
        $this->publicUrlLocal = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function test_an_old_file_no_row_references_is_reported_on_its_filesystem(): void
    {
        $this->write($this->local, 'orphan-00000001.png', self::OLD);
        $this->write($this->local, 'components/orphan-00000002.png', self::OLD);
        $this->write($this->publicUrlLocal, 'other-00000003.png', self::OLD);

        $report = $this->detector()->detect();

        self::assertSame([
            ['adapter' => 'local', 'path' => 'components/orphan-00000002.png'],
            ['adapter' => 'local', 'path' => 'orphan-00000001.png'],
            ['adapter' => 'public_url_local', 'path' => 'other-00000003.png'],
        ], $report->orphanedFiles);
        self::assertSame([], $report->missingFiles);
        self::assertSame([], $report->unknownFiles);
        self::assertEqualsWithDelta(new \DateTimeImmutable(), $report->generatedAt, 5);
    }

    public function test_a_file_any_row_references_is_not_reported_including_one_only_a_draft_or_a_second_field_holds(): void
    {
        $uploadable = new DummyUploadable();
        $uploadable->setFilename('used.png');
        $this->persist($uploadable);
        $multiple = new DummyMultipleUploadable();
        $multiple->previewFilename = 'preview.png';
        $this->persist($multiple);
        $draft = new DummyUploadableAndPublishable();
        $draft->setPublishedAt(null);
        $draft->setFilename('components/draft.png');
        $this->persist($draft);
        $this->entityManager->flush();
        foreach (['used.png', 'preview.png', 'components/draft.png', 'orphan-00000001.png'] as $path) {
            $this->write($this->local, $path, self::OLD);
        }

        self::assertSame([['adapter' => 'local', 'path' => 'orphan-00000001.png']], $this->detector()->detect()->orphanedFiles);
    }

    public function test_a_path_a_field_on_another_filesystem_references_is_not_reported_because_two_adapters_can_share_one_storage(): void
    {
        $publicUrl = new DummyUploadablePublicUrl();
        $publicUrl->setFilename('shared.png');
        $this->persist($publicUrl);
        $this->entityManager->flush();
        $this->write($this->local, 'shared.png', self::OLD);
        $this->write($this->publicUrlLocal, 'shared.png', self::OLD);

        self::assertSame([], $this->detector()->detect()->orphanedFiles);
    }

    public function test_an_unreferenced_file_the_bundle_did_not_name_and_never_served_is_unknown_not_orphaned(): void
    {
        $this->write($this->local, 'logo.png', self::OLD);
        $this->write($this->local, 'components/site-logo.png', self::OLD);
        $this->write($this->local, 'token-0a1b2c3d.png', self::OLD);
        $this->write($this->local, '0f1b2c3d-4e5f-4a7b-8c9d-0e1f2a3b4c5d.png', self::OLD);

        $report = $this->detector()->detect();

        self::assertSame([
            ['adapter' => 'local', 'path' => '0f1b2c3d-4e5f-4a7b-8c9d-0e1f2a3b4c5d.png'],
            ['adapter' => 'local', 'path' => 'token-0a1b2c3d.png'],
        ], $report->orphanedFiles);
        self::assertSame([
            ['adapter' => 'local', 'path' => 'components/site-logo.png'],
            ['adapter' => 'local', 'path' => 'logo.png'],
        ], $report->unknownFiles);
    }

    public function test_an_unreferenced_file_with_file_info_is_orphaned_whatever_its_name(): void
    {
        $this->entityManager->persist(new FileInfo('served-before-194.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('/nested//variant-only.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->write($this->local, 'served-before-194.png', self::OLD);
        $this->write($this->local, 'nested/variant-only.png', self::OLD);
        $this->write($this->local, 'never-served.png', self::OLD);

        $report = $this->detector()->detect();

        self::assertSame([
            ['adapter' => 'local', 'path' => 'nested/variant-only.png'],
            ['adapter' => 'local', 'path' => 'served-before-194.png'],
        ], $report->orphanedFiles);
        self::assertSame([['adapter' => 'local', 'path' => 'never-served.png']], $report->unknownFiles);
    }

    public function test_a_referenced_file_is_never_unknown_and_unknown_files_obey_the_minimum_age_and_exclusions(): void
    {
        $uploadable = new DummyUploadable();
        $uploadable->setFilename('logo.png');
        $this->persist($uploadable);
        $this->entityManager->flush();
        $this->write($this->local, 'logo.png', self::OLD);
        $this->write($this->local, 'new-logo.png', 10);
        $this->write($this->local, 'exports/report.csv', self::OLD);
        $this->write($this->local, 'cache/thumbnail/logo.png', self::OLD);

        $report = $this->detector(excludedPaths: ['exports/'], cacheResolvers: [new FlysystemCacheResolver($this->local, '/', 'cache')])->detect();

        self::assertSame([], $report->unknownFiles);
        self::assertSame([], $report->orphanedFiles);
    }

    public function test_a_stored_path_is_compared_after_flysystem_normalises_it(): void
    {
        $uploadable = new DummyUploadable();
        $uploadable->setFilename('/nested//used.png');
        $this->persist($uploadable);
        $this->entityManager->flush();
        $this->write($this->local, 'nested/used.png', self::OLD);

        $report = $this->detector()->detect();

        self::assertSame([], $report->orphanedFiles);
        self::assertSame([], $report->missingFiles);
    }

    public function test_a_file_younger_than_the_minimum_age_is_not_reported_and_one_exactly_that_old_is(): void
    {
        $this->write($this->local, 'new-00000005.png', 50);
        $this->write($this->local, 'old-00000004.png', 100);

        self::assertSame([['adapter' => 'local', 'path' => 'old-00000004.png']], $this->detector(minimumAge: 100)->detect()->orphanedFiles);
    }

    public function test_a_minimum_age_of_zero_reports_a_file_written_now(): void
    {
        $this->write($this->local, 'new-00000005.png', 0);

        self::assertSame([['adapter' => 'local', 'path' => 'new-00000005.png']], $this->detector(minimumAge: 0)->detect()->orphanedFiles);
    }

    public function test_a_file_whose_age_cannot_be_read_is_not_reported(): void
    {
        $this->local = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function listContents(string $path, bool $deep): iterable
            {
                foreach (parent::listContents($path, $deep) as $item) {
                    yield $item instanceof FileAttributes ? new FileAttributes($item->path()) : $item;
                }
            }

            public function lastModified(string $path): FileAttributes
            {
                if ('unreadable-00000006.png' === $path) {
                    throw UnableToRetrieveMetadata::lastModified($path);
                }

                return parent::lastModified($path);
            }
        });
        $this->write($this->local, 'unreadable-00000006.png', self::OLD);
        $this->write($this->local, 'readable-00000007.png', self::OLD);

        self::assertSame([['adapter' => 'local', 'path' => 'readable-00000007.png']], $this->detector()->detect()->orphanedFiles);
    }

    public function test_excluded_paths_and_imagine_cache_prefixes_are_excluded_on_every_scanned_filesystem(): void
    {
        foreach ([$this->local, $this->publicUrlLocal] as $filesystem) {
            $this->write($filesystem, 'exports/report.csv', self::OLD);
            $this->write($filesystem, 'media/cache/thumbnail/a.png', self::OLD);
            $this->write($filesystem, 'cache/thumbnail/a.png', self::OLD);
            $this->write($filesystem, 'media/a-00000008.png', self::OLD);
        }
        $resolvers = [
            new FlysystemCacheResolver(new Filesystem(new InMemoryFilesystemAdapter()), '/', 'media/cache'),
            new FlysystemCacheResolver($this->local, '/', 'cache/'),
            $this->createStub(ResolverInterface::class),
        ];

        self::assertSame([
            ['adapter' => 'local', 'path' => 'media/a-00000008.png'],
            ['adapter' => 'public_url_local', 'path' => 'media/a-00000008.png'],
        ], $this->detector(excludedPaths: ['/exports/'], cacheResolvers: $resolvers)->detect()->orphanedFiles);
    }

    public function test_an_imagine_cache_with_no_prefix_leaves_nothing_the_scan_can_prove_orphaned(): void
    {
        $this->write($this->local, 'orphan-00000001.png', self::OLD);

        $report = $this->detector(cacheResolvers: [new FlysystemCacheResolver($this->local, '/', '')])->detect();

        self::assertSame([], $report->orphanedFiles);
    }

    public function test_a_row_whose_file_is_missing_is_reported_with_its_resource_and_never_as_orphaned(): void
    {
        $missing = new DummyUploadable();
        $missing->setFilename('missing.png');
        $this->persist($missing);
        $draft = new DummyUploadableAndPublishable();
        $draft->setPublishedAt(null);
        $draft->setFilename('components/missing.png');
        $this->persist($draft);
        $present = new DummyUploadable();
        $present->setFilename('present.png');
        $this->persist($present);
        $this->entityManager->flush();
        $this->write($this->local, 'present.png', self::OLD);

        $report = $this->detector()->detect();

        self::assertSame([
            ['resource' => $this->iri($draft), 'adapter' => 'local', 'path' => 'components/missing.png'],
            ['resource' => $this->iri($missing), 'adapter' => 'local', 'path' => 'missing.png'],
        ], $report->missingFiles);
        self::assertSame([], $report->orphanedFiles);
    }

    public function test_a_referenced_file_outside_the_scanned_paths_is_checked_directly_before_it_is_called_missing(): void
    {
        $excluded = new DummyUploadable();
        $excluded->setFilename('exports/present.png');
        $this->persist($excluded);
        $gone = new DummyUploadable();
        $gone->setFilename('exports/gone.png');
        $this->persist($gone);
        $this->entityManager->flush();
        $this->write($this->local, 'exports/present.png', self::OLD);

        self::assertSame(
            [['resource' => $this->iri($gone), 'adapter' => 'local', 'path' => 'exports/gone.png']],
            $this->detector(excludedPaths: ['exports/'])->detect()->missingFiles
        );
    }

    public function test_the_scanned_adapters_are_those_uploadable_fields_use(): void
    {
        $adapters = $this->detector()->scannedAdapters();
        sort($adapters);

        self::assertSame(['local', 'public_url_local'], $adapters);
    }

    public function test_the_references_are_read_with_one_query_per_uploadable_class_whatever_the_number_of_rows(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $uploadable = new DummyUploadable();
            $uploadable->setFilename(\sprintf('file-%d.png', $i));
            $this->persist($uploadable);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        $this->detector()->detect();
        $fewRows = \count($this->queryLogger->queries);

        for ($i = 0; $i < 5; ++$i) {
            $uploadable = new DummyUploadable();
            $uploadable->setFilename(\sprintf('more-%d.png', $i));
            $this->persist($uploadable);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        $this->detector()->detect();

        self::assertCount($fewRows, $this->queryLogger->queries);
        self::assertSame(\count($this->uploadableClasses()) + 1, $fewRows);
        foreach ($this->queryLogger->queries as $query) {
            self::assertStringStartsWith('SELECT', $query);
        }
    }

    /**
     * @return list<string>
     */
    private function uploadableClasses(): array
    {
        $reader = new UploadableAttributeReader($this->registry, true);
        $classes = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!$metadata->isMappedSuperclass && !$metadata->getReflectionClass()->isAbstract() && $reader->isConfigured($metadata->getName())) {
                $classes[] = $metadata->getName();
            }
        }

        return $classes;
    }

    private function write(Filesystem $filesystem, string $path, int $age): void
    {
        $filesystem->write($path, $path, [Config::OPTION_VISIBILITY => 'public', 'timestamp' => time() - $age]);
    }

    /**
     * @param list<string>     $excludedPaths
     * @param iterable<object> $cacheResolvers
     */
    private function detector(int $minimumAge = 3600, array $excludedPaths = [], iterable $cacheResolvers = []): OrphanedFileDetector
    {
        return new OrphanedFileDetector(
            $this->registry,
            new UploadableAttributeReader($this->registry, true),
            new FilesystemProvider(new ServiceLocator([
                'local' => fn () => $this->local,
                'public_url_local' => fn () => $this->publicUrlLocal,
            ])),
            $this->iriConverter,
            new StoredFileLister(),
            new StoredFileNameMatcher(),
            new FileInfoRepository($this->registry),
            $cacheResolvers,
            $excludedPaths,
            $minimumAge,
        );
    }
}
