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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;

class FileInfoCacheManagerTest extends TestCase
{
    private EntityManager $entityManager;
    private FileInfoCacheManager $manager;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../../src/Entity/Core'], true);
        $configuration->enableNativeLazyObjects(true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        (new SchemaTool($this->entityManager))->createSchema([$this->entityManager->getClassMetadata(FileInfo::class)]);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->manager = new FileInfoCacheManager($this->entityManager, new FileInfoRepository($registry));

        foreach ([['a.png', null], ['a.png', 'thumbnail'], ['a.png', 'square'], ['b.png', null], ['b.png', 'thumbnail'], ['c.png', null]] as [$path, $filter]) {
            $this->entityManager->persist(new FileInfo($path, 'image/png', 1, 1, 1, $filter));
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function test_deleting_caches_removes_the_rows_immediately_without_a_flush(): void
    {
        $this->manager->deleteCaches(['a.png'], [null]);

        self::assertSame(['a.png:square', 'a.png:thumbnail', 'b.png:', 'b.png:thumbnail', 'c.png:'], $this->remainingRows());
    }

    public function test_deleting_caches_with_no_filters_removes_every_variant_of_each_path_and_keeps_the_originals(): void
    {
        $this->manager->deleteCaches(['a.png', 'b.png'], null);

        self::assertSame(['a.png:', 'b.png:', 'c.png:'], $this->remainingRows());
    }

    public function test_deleting_caches_for_named_filters_removes_only_those(): void
    {
        $this->manager->deleteCaches(['a.png', 'b.png'], ['thumbnail', null]);

        self::assertSame(['a.png:square', 'c.png:'], $this->remainingRows());
    }

    public function test_deleting_caches_for_no_paths_removes_those_filters_for_every_path(): void
    {
        $this->manager->deleteCaches([], ['thumbnail']);

        self::assertSame(['a.png:', 'a.png:square', 'b.png:', 'c.png:'], $this->remainingRows());
    }

    public function test_saving_a_cache_writes_its_row_without_flushing_other_pending_changes(): void
    {
        $pending = $this->entityManager->getRepository(FileInfo::class)->findOneBy(['path' => 'c.png']);
        $pending->mimeType = 'image/jpeg';
        $this->entityManager->persist(new FileInfo('e.png', 'image/png', 1, 1, 1, null));

        $this->manager->saveCache(new FileInfo('d.png', 'image/webp', 2048, 640, 480, 'thumbnail'));

        self::assertSame(['a.png:', 'a.png:square', 'a.png:thumbnail', 'b.png:', 'b.png:thumbnail', 'c.png:', 'd.png:thumbnail'], $this->remainingRows());
        self::assertSame(['image/png'], $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT mime_type FROM %s WHERE path = 'c.png'", $this->tableName())));
    }

    public function test_a_saved_cache_resolves_with_every_value_it_was_saved_with(): void
    {
        $this->manager->saveCache(new FileInfo('d.png', 'image/webp', 2048, 640, 480, 'thumbnail'));
        $this->entityManager->clear();

        $resolved = $this->manager->resolveCache('d.png', 'thumbnail');

        self::assertNotNull($resolved);
        self::assertNotNull($resolved->getId());
        self::assertSame(['image/webp', 2048, 640, 480, 'thumbnail'], [$resolved->mimeType, $resolved->fileSize, $resolved->width, $resolved->height, $resolved->filter]);
    }

    public function test_saving_a_cache_that_already_exists_keeps_the_existing_row(): void
    {
        $this->manager->saveCache(new FileInfo('a.png', 'image/gif', 99, 9, 9, 'thumbnail'));

        self::assertCount(6, $this->remainingRows());
        self::assertSame(['image/png'], $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT mime_type FROM %s WHERE path = 'a.png' AND filter = 'thumbnail'", $this->tableName())));
    }

    private function tableName(): string
    {
        return $this->entityManager->getClassMetadata(FileInfo::class)->getTableName();
    }

    public function test_a_row_inserted_by_a_concurrent_request_after_the_check_is_kept(): void
    {
        $repository = $this->createStub(FileInfoRepository::class);
        $repository->method('findOneByPathAndFilter')->willReturn(null);
        $racingManager = new FileInfoCacheManager($this->entityManager, $repository);

        $racingManager->saveCache(new FileInfo('a.png', 'image/gif', 99, 9, 9, null));

        self::assertCount(6, $this->remainingRows());
        self::assertSame(['image/png'], $this->entityManager->getConnection()->fetchFirstColumn(\sprintf("SELECT mime_type FROM %s WHERE path = 'a.png' AND filter = ''", $this->tableName())));
    }

    /**
     * @return list<string>
     */
    private function remainingRows(): array
    {
        $rows = $this->entityManager->getConnection()->fetchFirstColumn(
            \sprintf("SELECT path || ':' || COALESCE(filter, '') FROM %s ORDER BY 1", $this->entityManager->getClassMetadata(FileInfo::class)->getTableName())
        );

        return array_values($rows);
    }
}
