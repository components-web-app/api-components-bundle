<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Uploadable;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Psr\Log\AbstractLogger;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImagineVariant;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImagineVariantStatus;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyMultipleUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class ImagineVariantGeneratorTest extends OrphanedResourceDatabaseTestCase
{
    use ImagineVariantGeneratorTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpImagine();
    }

    protected function tearDown(): void
    {
        $this->tearDownImagine();
    }

    public function test_every_unstored_filter_of_the_field_and_of_the_object_is_generated_once_and_a_stored_one_is_only_reported(): void
    {
        $withFilters = new DummyUploadableWithImagineFilters();
        $withFilters->setFilename('photo-1a2b3c4d.png');
        $this->persist($withFilters);
        $multiple = new DummyMultipleUploadable();
        $multiple->setFilename('document-1a2b3c4d.png');
        $multiple->previewFilename = 'preview-1a2b3c4d.png';
        $this->persist($multiple);
        $this->entityManager->flush();
        $this->writeImage('photo-1a2b3c4d.png');
        $this->writeImage('document-1a2b3c4d.png');
        $this->writeImage('preview-1a2b3c4d.png');
        $this->stored['square_thumbnail:photo-1a2b3c4d.png'] = true;

        $variants = $this->variants($this->imagineVariantGenerator()->generate());

        self::assertSame([
            [DummyMultipleUploadable::class, 'preview', 'preview-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Generated],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'square_thumbnail', ImagineVariantStatus::Stored],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Generated],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'webp_thumbnail', ImagineVariantStatus::Generated],
        ], $variants);
        self::assertEqualsCanonicalizing([
            ['thumbnail', 'preview-1a2b3c4d.png', 'local'],
            ['thumbnail', 'photo-1a2b3c4d.png', 'local'],
            ['webp_thumbnail', 'photo-1a2b3c4d.png', 'local'],
        ], $this->generated);
    }

    public function test_each_variant_is_generated_with_the_cache_control_of_its_source_field(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $multiple = new DummyMultipleUploadable();
        $multiple->previewFilename = 'preview-1a2b3c4d.png';
        $this->persist($multiple);
        $this->entityManager->flush();
        $this->writeImage('preview-1a2b3c4d.png');

        iterator_to_array($this->imagineVariantGenerator()->generate(filters: ['thumbnail']), false);

        self::assertSame([
            'thumbnail:photo-1a2b3c4d.png' => 'public, max-age=2592000',
            'thumbnail:preview-1a2b3c4d.png' => 'public, max-age=60',
        ], $this->sortedByKey($this->cacheControlAtGeneration));
    }

    public function test_a_second_run_generates_nothing_and_reports_every_variant_as_stored(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $generator = $this->imagineVariantGenerator();
        iterator_to_array($generator->generate(), false);
        $this->generated = [];

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($generator->generate()));

        self::assertSame([ImagineVariantStatus::Stored, ImagineVariantStatus::Stored, ImagineVariantStatus::Stored], $statuses);
        self::assertSame([], $this->generated);
    }

    public function test_a_dry_run_reports_each_unstored_variant_as_pending_and_generates_nothing(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;

        $variants = $this->variants($this->imagineVariantGenerator()->generate(dryRun: true));

        self::assertSame([
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'square_thumbnail', ImagineVariantStatus::Pending],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Stored],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'webp_thumbnail', ImagineVariantStatus::Pending],
        ], $variants);
        self::assertSame([], $this->generated);
    }

    public function test_only_the_given_filters_are_considered_and_a_filter_no_field_uses_matches_nothing(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');

        $variants = $this->variants($this->imagineVariantGenerator()->generate(filters: ['webp_thumbnail', 'large']));

        self::assertSame([
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'webp_thumbnail', ImagineVariantStatus::Generated],
        ], $variants);
    }

    public function test_only_the_given_classes_are_walked(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $multiple = new DummyMultipleUploadable();
        $multiple->previewFilename = 'preview-1a2b3c4d.png';
        $this->persist($multiple);
        $this->entityManager->flush();
        $this->writeImage('preview-1a2b3c4d.png');

        $variants = $this->variants($this->imagineVariantGenerator()->generate(classes: [DummyMultipleUploadable::class]));

        self::assertSame([
            [DummyMultipleUploadable::class, 'preview', 'preview-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Generated],
        ], $variants);
    }

    public function test_the_uploadable_classes_are_the_concrete_entities_with_uploadable_fields(): void
    {
        $classes = $this->imagineVariantGenerator()->uploadableClasses();

        self::assertContains(DummyUploadable::class, $classes);
        self::assertContains(DummyUploadableWithImagineFilters::class, $classes);
        self::assertContains(DummyUploadableAndPublishable::class, $classes);
        self::assertNotContains(\Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent::class, $classes);
        self::assertNotContains(\Silverback\ApiComponentsBundle\Entity\Core\Page::class, $classes);
        self::assertSame(array_values(array_unique($classes)), $classes);
    }

    public function test_a_file_that_is_not_a_raster_image_is_left_out(): void
    {
        $vector = new DummyUploadableWithImagineFilters();
        $vector->setFilename('logo-1a2b3c4d.svg');
        $this->persist($vector);
        $document = new DummyUploadableWithImagineFilters();
        $document->setFilename('notes-1a2b3c4d.txt');
        $this->persist($document);
        $this->entityManager->flush();
        $this->local->write('logo-1a2b3c4d.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');
        $this->local->write('notes-1a2b3c4d.txt', 'plain text');

        self::assertSame([], $this->variants($this->imagineVariantGenerator()->generate()));
        self::assertSame([], $this->generated);
    }

    public function test_a_raster_image_known_from_its_file_info_is_generated_without_asking_the_filesystem_for_its_type(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png', write: false);
        $this->local->write('photo-1a2b3c4d.png', 'not an image header');
        $this->fileInfo('photo-1a2b3c4d.png', 'image/png', 10, 10);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate()));

        self::assertSame([ImagineVariantStatus::Generated, ImagineVariantStatus::Generated, ImagineVariantStatus::Generated], $statuses);
    }

    public function test_an_image_whose_file_info_is_not_a_raster_image_is_left_out(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->fileInfo('photo-1a2b3c4d.png', 'image/svg+xml', 10, 10);

        self::assertSame([], $this->variants($this->imagineVariantGenerator()->generate()));
    }

    public function test_a_field_with_no_stored_file_is_left_out(): void
    {
        $multiple = new DummyMultipleUploadable();
        $multiple->setFilename('document-1a2b3c4d.png');
        $this->persist($multiple);
        $this->persist(new DummyUploadableWithImagineFilters());
        $this->entityManager->flush();
        $this->writeImage('document-1a2b3c4d.png');

        self::assertSame([], $this->variants($this->imagineVariantGenerator()->generate()));
    }

    public function test_a_missing_original_reports_each_unstored_filter_and_generates_nothing(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png', write: false);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate()));

        self::assertSame([ImagineVariantStatus::OriginalMissing, ImagineVariantStatus::OriginalMissing, ImagineVariantStatus::OriginalMissing], $statuses);
        self::assertSame([], $this->generated);
    }

    public function test_an_image_over_the_memory_budget_is_skipped_using_the_dimensions_in_its_file_info(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->fileInfo('photo-1a2b3c4d.png', 'image/png', 8202, 5468);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate()));

        self::assertSame([ImagineVariantStatus::Skipped, ImagineVariantStatus::Skipped, ImagineVariantStatus::Skipped], $statuses);
        self::assertSame([], $this->generated);
    }

    public function test_an_image_over_the_memory_budget_is_skipped_using_the_dimensions_read_from_the_file_when_it_has_no_file_info(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png', width: 8202, height: 5468);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate()));

        self::assertSame([ImagineVariantStatus::Skipped, ImagineVariantStatus::Skipped, ImagineVariantStatus::Skipped], $statuses);
    }

    public function test_an_image_whose_file_info_has_no_dimensions_is_generated(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png', width: 8202, height: 5468);
        $this->fileInfo('photo-1a2b3c4d.png', 'image/png', null, null);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate()));

        self::assertSame([ImagineVariantStatus::Generated, ImagineVariantStatus::Generated, ImagineVariantStatus::Generated], $statuses);
    }

    public function test_a_filter_that_fails_is_reported_and_the_others_still_run(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->failures['thumbnail'] = new \RuntimeException('vips failed');

        $variants = $this->variants($this->imagineVariantGenerator()->generate());

        self::assertSame([
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'square_thumbnail', ImagineVariantStatus::Generated],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Failed],
            [DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'webp_thumbnail', ImagineVariantStatus::Generated],
        ], $variants);
    }

    public function test_a_filter_whose_stored_check_fails_is_reported_as_failed_and_logged(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $failure = new \RuntimeException('Could not find configuration for a filter: thumbnail');
        $this->storedCheckFailures['thumbnail'] = $failure;
        $logger = $this->logger();

        $variants = $this->variants($this->imagineVariantGenerator(logger: $logger)->generate());

        self::assertSame(ImagineVariantStatus::Failed, $variants[1][4]);
        self::assertSame('thumbnail', $variants[1][3]);
        self::assertSame(ImagineVariantStatus::Generated, $variants[0][4]);
        self::assertSame(ImagineVariantStatus::Generated, $variants[2][4]);
        self::assertSame([['error', 'thumbnail', 'photo-1a2b3c4d.png', $failure]], $logger->records);
    }

    public function test_an_original_that_cannot_be_read_reports_each_filter_as_failed_and_logs_it(): void
    {
        $failure = UnableToRetrieveMetadata::mimeType('photo-1a2b3c4d.png');
        $this->local = new Filesystem(new class($failure) extends InMemoryFilesystemAdapter {
            public function __construct(private readonly UnableToRetrieveMetadata $failure)
            {
                parent::__construct();
            }

            public function mimeType(string $path): \League\Flysystem\FileAttributes
            {
                throw $this->failure;
            }
        });
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $logger = $this->logger();

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator(logger: $logger)->generate()));

        self::assertSame([ImagineVariantStatus::Failed, ImagineVariantStatus::Failed, ImagineVariantStatus::Failed], $statuses);
        self::assertSame([['error', null, 'photo-1a2b3c4d.png', $failure]], $logger->records);
        self::assertSame([], $this->generated);
    }

    public function test_an_original_whose_content_cannot_be_read_for_its_dimensions_reports_each_unstored_filter_as_failed(): void
    {
        $failure = UnableToReadFile::fromLocation('photo-1a2b3c4d.png');
        $this->local = new Filesystem(new class($failure) extends InMemoryFilesystemAdapter {
            public function __construct(private readonly UnableToReadFile $failure)
            {
                parent::__construct();
            }

            public function read(string $path): string
            {
                throw $this->failure;
            }
        });
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;
        $logger = $this->logger();

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator(logger: $logger)->generate()));

        self::assertSame([ImagineVariantStatus::Failed, ImagineVariantStatus::Stored, ImagineVariantStatus::Failed], $statuses);
        self::assertSame([['error', null, 'photo-1a2b3c4d.png', $failure]], $logger->records);
        self::assertSame([], $this->generated);
    }

    public function test_an_unreadable_original_needs_no_logger(): void
    {
        $this->local = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function mimeType(string $path): \League\Flysystem\FileAttributes
            {
                throw UnableToRetrieveMetadata::mimeType($path);
            }
        });
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->storedCheckFailures['thumbnail'] = new \RuntimeException('no filter');

        self::assertCount(3, $this->variants($this->imagineVariantGenerator()->generate()));
    }

    public function test_a_failing_stored_check_needs_no_logger(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->storedCheckFailures['thumbnail'] = new \RuntimeException('no filter');

        self::assertCount(3, $this->variants($this->imagineVariantGenerator()->generate()));
    }

    public function test_rows_are_read_in_batches_and_the_entity_manager_is_cleared_between_them(): void
    {
        foreach (['photo-00000001.png', 'photo-00000002.png', 'photo-00000003.png', 'photo-00000004.png'] as $path) {
            $this->persistWithImagineFilters($path);
        }
        $this->entityManager->clear();

        $paths = [];
        $managedAtEachVariant = [];
        foreach ($this->imagineVariantGenerator(batchSize: 2)->generate(filters: ['thumbnail']) as $variant) {
            $paths[] = $variant->path;
            $managedAtEachVariant[] = \count($this->entityManager->getUnitOfWork()->getIdentityMap()[DummyUploadableWithImagineFilters::class] ?? []);
        }

        sort($paths);
        self::assertSame(['photo-00000001.png', 'photo-00000002.png', 'photo-00000003.png', 'photo-00000004.png'], $paths);
        self::assertSame([2, 2, 2, 2], $managedAtEachVariant);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    public function test_a_stop_ends_the_whole_run_and_leaves_the_entity_manager_clear(): void
    {
        $this->persistWithImagineFilters('photo-00000001.png');
        foreach (['preview-00000002.png', 'preview-00000003.png'] as $path) {
            $multiple = new DummyMultipleUploadable();
            $multiple->previewFilename = $path;
            $this->persist($multiple);
            $this->writeImage($path);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
        $asked = 0;

        $variants = $this->variants($this->imagineVariantGenerator()->generate(proceed: static function () use (&$asked): bool {
            return 1 === ++$asked;
        }));

        self::assertSame(2, $asked);
        self::assertCount(1, $variants);
        self::assertSame(ImagineVariantStatus::Generated, $variants[0][4]);
        self::assertCount(1, $this->generated);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    public function test_a_row_whose_original_type_cannot_be_read_does_not_stop_the_rows_after_it(): void
    {
        $this->local = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public ?string $unreadable = null;

            public function mimeType(string $path): \League\Flysystem\FileAttributes
            {
                if ($path === $this->unreadable) {
                    throw UnableToRetrieveMetadata::mimeType($path);
                }

                return parent::mimeType($path);
            }
        });
        [$first, $second] = $this->persistTwoInOrder();
        $this->writeImage($first);
        $this->writeImage($second);
        $this->setUnreadable($first);

        self::assertSame([[$first, ImagineVariantStatus::Failed], [$second, ImagineVariantStatus::Generated]], $this->thumbnailStatuses());
    }

    public function test_a_row_whose_original_is_missing_does_not_stop_the_rows_after_it(): void
    {
        [$first, $second] = $this->persistTwoInOrder();
        $this->writeImage($second);

        self::assertSame([[$first, ImagineVariantStatus::OriginalMissing], [$second, ImagineVariantStatus::Generated]], $this->thumbnailStatuses());
    }

    public function test_a_row_that_is_not_a_raster_image_does_not_stop_the_rows_after_it(): void
    {
        [$first, $second] = $this->persistTwoInOrder();
        $this->local->write($first, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');
        $this->writeImage($second);

        self::assertSame([[$second, ImagineVariantStatus::Generated]], $this->thumbnailStatuses());
    }

    public function test_a_row_whose_original_cannot_be_read_for_its_dimensions_does_not_stop_the_rows_after_it(): void
    {
        $this->local = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public ?string $unreadable = null;

            public function read(string $path): string
            {
                if ($path === $this->unreadable) {
                    throw UnableToReadFile::fromLocation($path);
                }

                return parent::read($path);
            }
        });
        [$first, $second] = $this->persistTwoInOrder();
        $this->writeImage($first);
        $this->writeImage($second);
        $this->setUnreadable($first);

        self::assertSame([[$first, ImagineVariantStatus::Failed], [$second, ImagineVariantStatus::Generated]], $this->thumbnailStatuses());
    }

    public function test_a_row_whose_variants_are_all_stored_never_reads_its_original(): void
    {
        $this->local = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function read(string $path): string
            {
                throw UnableToReadFile::fromLocation($path);
            }
        });
        $this->persistWithImagineFilters('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;
        $logger = $this->logger();

        $variants = $this->variants($this->imagineVariantGenerator(logger: $logger)->generate(filters: ['thumbnail']));

        self::assertSame([[DummyUploadableWithImagineFilters::class, 'file', 'photo-1a2b3c4d.png', 'thumbnail', ImagineVariantStatus::Stored]], $variants);
        self::assertSame([], $logger->records);
    }

    public function test_an_image_over_the_memory_budget_only_by_its_width_times_its_height_is_skipped(): void
    {
        $this->persistWithImagineFilters('photo-1a2b3c4d.png', width: 3000, height: 20000);

        $statuses = array_map(static fn (array $variant): ImagineVariantStatus => $variant[4], $this->variants($this->imagineVariantGenerator()->generate(filters: ['thumbnail'])));

        self::assertSame([ImagineVariantStatus::Skipped], $statuses);
    }

    /**
     * @return array{string, string}
     */
    private function persistTwoInOrder(): array
    {
        $rows = [];
        foreach (['photo-00000001.png', 'photo-00000002.png'] as $path) {
            $object = new DummyUploadableWithImagineFilters();
            $object->setFilename($path);
            $this->persist($object);
            $rows[] = $object;
        }
        $this->entityManager->flush();
        usort($rows, static fn (DummyUploadableWithImagineFilters $a, DummyUploadableWithImagineFilters $b): int => strcmp((string) $a->getId(), (string) $b->getId()));
        $this->entityManager->clear();

        return [(string) $rows[0]->getFilename(), (string) $rows[1]->getFilename()];
    }

    private function setUnreadable(string $path): void
    {
        $adapter = (new \ReflectionProperty(Filesystem::class, 'adapter'))->getValue($this->local);
        \assert(property_exists($adapter, 'unreadable'));
        $adapter->unreadable = $path;
    }

    /**
     * @return list<array{string, ImagineVariantStatus}>
     */
    private function thumbnailStatuses(): array
    {
        $statuses = [];
        foreach ($this->imagineVariantGenerator()->generate(filters: ['thumbnail']) as $variant) {
            $statuses[] = [$variant->path, $variant->status];
        }

        return $statuses;
    }

    private function persistWithImagineFilters(string $path, bool $write = true, int $width = 10, int $height = 10): DummyUploadableWithImagineFilters
    {
        $object = new DummyUploadableWithImagineFilters();
        $object->setFilename($path);
        $this->persist($object);
        $this->entityManager->flush();
        if ($write) {
            $this->writeImage($path, $width, $height);
        }

        return $object;
    }

    /**
     * @param iterable<ImagineVariant> $variants
     *
     * @return list<array{class-string, string, string, string, ImagineVariantStatus}>
     */
    private function variants(iterable $variants): array
    {
        $rows = [];
        foreach ($variants as $variant) {
            $rows[] = [$variant->class, $variant->field, $variant->path, $variant->filter, $variant->status];
        }
        usort($rows, static fn (array $a, array $b): int => [$a[0], $a[2], $a[3]] <=> [$b[0], $b[2], $b[3]]);

        return $rows;
    }

    /**
     * @param array<string, ?string> $values
     *
     * @return array<string, ?string>
     */
    private function sortedByKey(array $values): array
    {
        ksort($values);

        return $values;
    }

    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{mixed, mixed, mixed, mixed}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [$level, $context['filter'] ?? null, $context['path'] ?? null, $context['exception'] ?? null];
            }
        };
    }
}
