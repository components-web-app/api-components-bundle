<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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

    /**
     * @return FileInfo[]
     */
    public function findByPathsAndFilters(array $paths, ?array $filters): array
    {
        if (!\count($paths)) {
            return [];
        }

        $queryBuilder = $this->createQueryBuilder('f');
        $expr = $queryBuilder->expr();

        $filterQueries = [];
        if ($filters) {
            foreach ($filters as $filterIndex => $filter) {
                if (!$filter) {
                    $filterQueries[] = $expr->isNull('f.filter');
                    continue;
                }
                $filterQueries[] = $expr->eq('f.filter', ':filter_' . $filterIndex);
                $queryBuilder->setParameter(':filter_' . $filterIndex, $filter);
            }
        }

        foreach ($paths as $pathIndex => $path) {
            $queryBuilder->setParameter(':path_' . $pathIndex, $path);
            if (!\count($filterQueries)) {
                $queryBuilder
                    ->orWhere(
                        $expr->eq('f.path', ':path_' . $pathIndex)
                    );
                continue;
            }

            $queryBuilder
                ->orWhere(
                    $expr->andX(
                        $expr->eq('f.path', ':path_' . $pathIndex),
                        $expr->orX(...$filterQueries)
                    )
                );
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function findOneByPathAndFilter(string $path, ?string $filter): ?FileInfo
    {
        return $this->findOneBy([
            'path' => $path,
            'filter' => $filter,
        ]);
    }
}
