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
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Command\DeduplicateFileInfoCommand;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoDeduplicator;
use Symfony\Component\Console\Tester\CommandTester;

class FileInfoDeduplicatorTest extends TestCase
{
    private EntityManager $entityManager;
    private string $table;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../../../src/Entity/Core'], true);
        $configuration->enableNativeLazyObjects(true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        $this->table = $this->entityManager->getClassMetadata(FileInfo::class)->getTableName();
        $this->entityManager->getConnection()->executeStatement(\sprintf(
            'CREATE TABLE %s (path VARCHAR(255) NOT NULL, mime_type VARCHAR(255) NOT NULL, file_size INTEGER NOT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, filter VARCHAR(255) DEFAULT NULL, id CHAR(36) NOT NULL, PRIMARY KEY (id))',
            $this->table
        ));
    }

    public function test_originals_stored_with_a_null_filter_get_an_empty_one_and_duplicates_are_removed(): void
    {
        $this->insertRows([
            ['a.png', null],
            ['a.png', null],
            ['a.png', 'thumbnail'],
            ['a.png', 'thumbnail'],
            ['a.png', 'thumbnail'],
            ['a.png', 'square'],
            ['b.png', null],
            ['b.png', ''],
            ['c.png', 'thumbnail'],
        ]);

        $result = (new FileInfoDeduplicator($this->entityManager))->deduplicate();

        self::assertSame(['converted' => 3, 'removed' => 4], $result);
        self::assertSame(['a.png:', 'a.png:square', 'a.png:thumbnail', 'b.png:', 'c.png:thumbnail'], $this->rows());
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->insertRows([['a.png', null], ['a.png', null], ['a.png', 'thumbnail']]);
        $deduplicator = new FileInfoDeduplicator($this->entityManager);
        $deduplicator->deduplicate();

        self::assertSame(['converted' => 0, 'removed' => 0], $deduplicator->deduplicate());
        self::assertSame(['a.png:', 'a.png:thumbnail'], $this->rows());
    }

    public function test_an_empty_table_needs_nothing(): void
    {
        self::assertSame(['converted' => 0, 'removed' => 0], (new FileInfoDeduplicator($this->entityManager))->deduplicate());
    }

    public function test_duplicates_beyond_one_delete_batch_are_all_removed(): void
    {
        $this->insertRows(array_fill(0, 1203, ['a.png', 'thumbnail']));

        self::assertSame(['converted' => 0, 'removed' => 1202], (new FileInfoDeduplicator($this->entityManager))->deduplicate());
        self::assertSame(['a.png:thumbnail'], $this->rows());
    }

    public function test_the_command_reports_what_it_changed(): void
    {
        $this->insertRows([['a.png', null], ['a.png', null], ['a.png', 'thumbnail']]);
        $tester = new CommandTester(new DeduplicateFileInfoCommand(new FileInfoDeduplicator($this->entityManager)));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Set an empty filter on 2 original file info rows and removed 1 duplicate row.', $tester->getDisplay());

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Set an empty filter on 0 original file info rows and removed 0 duplicate rows.', $tester->getDisplay());
    }

    /**
     * @param list<array{string, ?string}> $rows
     */
    private function insertRows(array $rows): void
    {
        foreach ($rows as [$path, $filter]) {
            $this->entityManager->getConnection()->insert($this->table, [
                'id' => Uuid::uuid4()->toString(),
                'path' => $path,
                'mime_type' => 'image/png',
                'file_size' => 1,
                'filter' => $filter,
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function rows(): array
    {
        return array_values($this->entityManager->getConnection()->fetchFirstColumn(
            \sprintf("SELECT path || ':' || COALESCE(filter, 'NULL') FROM %s ORDER BY 1", $this->table)
        ));
    }
}
