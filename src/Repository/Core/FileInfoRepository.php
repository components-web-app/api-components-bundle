<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Repository\Core;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @method FileInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method FileInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method FileInfo[]    findAll()
 * @method FileInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileInfo::class);
    }

    public function findOneByPathAndFilter(string $path, ?string $filter): ?FileInfo
    {
        return $this->findOneBy(
            [
                'path' => $path,
                'storedFilter' => $filter ?? '',
            ]
        );
    }

    /**
     * @return list<string>
     */
    public function findPaths(): array
    {
        return array_values(array_map('strval', $this->createQueryBuilder('f')
            ->select('DISTINCT f.path')
            ->getQuery()
            ->getSingleColumnResult()));
    }

    /**
     * @return array<string, array{mimeType: string, fileSize: int, width: ?int, height: ?int}>
     */
    public function findOriginalsByPath(): array
    {
        $originals = [];
        $rows = $this->createQueryBuilder('f')
            ->select('f.path', 'f.mimeType', 'f.fileSize', 'f.width', 'f.height')
            ->where("f.storedFilter = ''")
            ->getQuery()
            ->getArrayResult();
        foreach ($rows as $row) {
            $originals[(string) $row['path']] = [
                'mimeType' => (string) $row['mimeType'],
                'fileSize' => (int) $row['fileSize'],
                'width' => null === $row['width'] ? null : (int) $row['width'],
                'height' => null === $row['height'] ? null : (int) $row['height'],
            ];
        }

        return $originals;
    }

    public function deleteByPathsAndFilters(array $paths, ?array $filters): void
    {
        if ([] === $filters) {
            return;
        }

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()->delete(FileInfo::class, 'f');
        if ([] !== $paths) {
            $queryBuilder->andWhere('f.path IN (:paths)')->setParameter('paths', $paths);
        }
        if (null === $filters) {
            $queryBuilder->andWhere("f.storedFilter <> ''");
        } else {
            $queryBuilder->andWhere('f.storedFilter IN (:filters)')->setParameter('filters', array_map(static fn (?string $filter): string => $filter ?? '', $filters));
        }

        $queryBuilder->getQuery()->execute();
    }
}
