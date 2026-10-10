<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\SchemaTool;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class FileInfoRepositoryTest extends OrphanedResourceDatabaseTestCase
{
    public function test_nothing_has_file_info_in_an_empty_table(): void
    {
        self::assertSame([], (new FileInfoRepository($this->registry))->findPaths());
    }

    public function test_every_path_with_file_info_is_listed_once_whatever_its_filters(): void
    {
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, 'square_thumbnail'));
        $this->entityManager->persist(new FileInfo('components/other.pdf', 'application/pdf', 1, null, null, null));
        $this->entityManager->persist(new FileInfo('variant-only.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        $paths = (new FileInfoRepository($this->registry))->findPaths();
        sort($paths);

        self::assertSame(['components/other.pdf', 'served.png', 'variant-only.png'], $paths);
        self::assertCount(1, $this->queryLogger->queries);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    public function test_the_originals_metadata_is_keyed_by_path_and_leaves_out_imagine_variants(): void
    {
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 2048, 640, 480, null));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 512, 100, 75, 'thumbnail'));
        $this->entityManager->persist(new FileInfo('components/other.pdf', 'application/pdf', 99, null, null, null));
        $this->entityManager->persist(new FileInfo('variant-only.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        $originals = (new FileInfoRepository($this->registry))->findOriginalsByPath();
        ksort($originals);

        self::assertSame([
            'components/other.pdf' => ['mimeType' => 'application/pdf', 'fileSize' => 99, 'width' => null, 'height' => null],
            'served.png' => ['mimeType' => 'image/png', 'fileSize' => 2048, 'width' => 640, 'height' => 480],
        ], $originals);
        self::assertCount(1, $this->queryLogger->queries);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());
    }

    public function test_the_schema_has_a_unique_index_on_path_and_filter_with_a_non_null_filter_column(): void
    {
        $metadata = $this->entityManager->getClassMetadata(FileInfo::class);
        $table = (new SchemaTool($this->entityManager))->getSchemaFromMetadata([$metadata])->getTable($metadata->getTableName());

        $unique = array_values(array_filter($table->getIndexes(), static fn ($index): bool => $index->isUnique() && !$index->isPrimary()));
        self::assertCount(1, $unique);
        self::assertSame('unique_cache_item', $unique[0]->getName());
        self::assertSame(['path', 'filter'], $unique[0]->getColumns());
        self::assertTrue($table->getColumn('filter')->getNotnull());
        self::assertSame('', $table->getColumn('filter')->getDefault());
    }

    public function test_two_originals_for_one_path_are_rejected_by_the_database(): void
    {
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, null));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function test_an_original_is_stored_with_an_empty_filter_and_reads_back_as_null(): void
    {
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = new FileInfoRepository($this->registry);
        $original = $repository->findOneByPathAndFilter('served.png', null);
        $variant = $repository->findOneByPathAndFilter('served.png', 'thumbnail');

        self::assertNotNull($original);
        self::assertNull($original->filter);
        self::assertSame('thumbnail', $variant?->filter);
        self::assertSame(
            ['', 'thumbnail'],
            $this->entityManager->getConnection()->fetchFirstColumn(\sprintf('SELECT filter FROM %s ORDER BY filter', $this->entityManager->getClassMetadata(FileInfo::class)->getTableName()))
        );
    }

    public function test_deleting_the_original_by_a_null_filter_keeps_its_variants(): void
    {
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, null));
        $this->entityManager->persist(new FileInfo('served.png', 'image/png', 1, 1, 1, 'thumbnail'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $repository = new FileInfoRepository($this->registry);
        $repository->deleteByPathsAndFilters(['served.png'], [null]);

        self::assertNull($repository->findOneByPathAndFilter('served.png', null));
        self::assertNotNull($repository->findOneByPathAndFilter('served.png', 'thumbnail'));
    }

    public function test_deleting_filters_with_no_paths_removes_those_filters_rows_for_every_path(): void
    {
        $repository = $this->repositoryWithRows();

        $repository->deleteByPathsAndFilters([], ['thumbnail']);

        self::assertSame(['a.png|', 'a.png|square', 'b.png|', 'b.png|square'], $this->rows());
    }

    public function test_deleting_with_no_paths_and_no_filters_removes_every_variant_row_and_keeps_the_originals(): void
    {
        $repository = $this->repositoryWithRows();

        $repository->deleteByPathsAndFilters([], null);

        self::assertSame(['a.png|', 'b.png|'], $this->rows());
    }

    public function test_deleting_a_path_with_no_filters_removes_its_variant_rows_and_keeps_its_original(): void
    {
        $repository = $this->repositoryWithRows();

        $repository->deleteByPathsAndFilters(['a.png'], null);

        self::assertSame(['a.png|', 'b.png|', 'b.png|square', 'b.png|thumbnail'], $this->rows());
    }

    public function test_deleting_a_path_and_a_filter_removes_only_that_row(): void
    {
        $repository = $this->repositoryWithRows();

        $repository->deleteByPathsAndFilters(['a.png'], ['thumbnail']);

        self::assertSame(['a.png|', 'a.png|square', 'b.png|', 'b.png|square', 'b.png|thumbnail'], $this->rows());
    }

    public function test_deleting_an_empty_list_of_filters_removes_nothing(): void
    {
        $repository = $this->repositoryWithRows();

        $repository->deleteByPathsAndFilters([], []);
        $repository->deleteByPathsAndFilters(['a.png'], []);

        self::assertCount(6, $this->rows());
    }

    private function repositoryWithRows(): FileInfoRepository
    {
        foreach (['a.png', 'b.png'] as $path) {
            foreach ([null, 'thumbnail', 'square'] as $filter) {
                $this->entityManager->persist(new FileInfo($path, 'image/png', 1, 1, 1, $filter));
            }
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        return new FileInfoRepository($this->registry);
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT path || '|' || filter FROM %s ORDER BY path, filter", $this->entityManager->getClassMetadata(FileInfo::class)->getTableName()));
    }
}
