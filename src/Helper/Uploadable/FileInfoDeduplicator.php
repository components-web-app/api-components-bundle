<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;

final class FileInfoDeduplicator
{
    private const int DELETE_BATCH_SIZE = 500;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{converted: int, removed: int}
     */
    public function deduplicate(): array
    {
        $classMetadata = $this->entityManager->getClassMetadata(FileInfo::class);
        $table = $classMetadata->getTableName();
        $id = $classMetadata->getColumnName('id');
        $path = $classMetadata->getColumnName('path');
        $filter = $classMetadata->getColumnName('storedFilter');
        $connection = $this->entityManager->getConnection();

        $converted = (int) $connection->executeStatement(\sprintf("UPDATE %s SET %s = '' WHERE %s IS NULL", $table, $filter, $filter));

        $kept = [];
        $duplicates = [];
        foreach ($connection->iterateAssociative(\sprintf('SELECT %s AS id, %s AS path, %s AS filter FROM %s ORDER BY %s', $id, $path, $filter, $table, $id)) as $row) {
            $key = $row['path'] . "\0" . $row['filter'];
            if (isset($kept[$key])) {
                $duplicates[] = (string) $row['id'];
                continue;
            }
            $kept[$key] = true;
        }

        foreach (array_chunk($duplicates, self::DELETE_BATCH_SIZE) as $batch) {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE %s IN (?)', $table, $id), [$batch], [ArrayParameterType::STRING]);
        }

        return ['converted' => $converted, 'removed' => \count($duplicates)];
    }
}
