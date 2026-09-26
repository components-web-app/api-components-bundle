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

    public function test_deleting_caches_with_no_filters_removes_every_filter_of_each_path(): void
    {
        $this->manager->deleteCaches(['a.png', 'b.png'], null);

        self::assertSame(['c.png:'], $this->remainingRows());
    }

    public function test_deleting_caches_for_named_filters_removes_only_those(): void
    {
        $this->manager->deleteCaches(['a.png', 'b.png'], ['thumbnail', null]);

        self::assertSame(['a.png:square', 'c.png:'], $this->remainingRows());
    }

    public function test_deleting_caches_for_no_paths_removes_nothing(): void
    {
        $this->manager->deleteCaches([], null);

        self::assertCount(6, $this->remainingRows());
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
