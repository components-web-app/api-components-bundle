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

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;

class FileInfoCacheManager
{
    private EntityManagerInterface $entityManager;
    private FileInfoRepository $repository;

    public function __construct(EntityManagerInterface $entityManager, FileInfoRepository $fileInfoRepository)
    {
        $this->entityManager = $entityManager;
        $this->repository = $fileInfoRepository;
    }

    public function saveCache(FileInfo $fileInfo): void
    {
        $this->entityManager->persist($fileInfo);
        $this->entityManager->flush();
    }

    public function deleteCaches(array $paths, ?array $filters): void
    {
        $results = $this->repository->findByPathsAndFilters($paths, $filters);
        foreach ($results as $result) {
            $this->entityManager->remove($result);
        }
        $this->entityManager->flush();
    }

    public function resolveCache(string $path, ?string $filter = null): ?FileInfo
    {
        return $this->repository->findOneByPathAndFilter($path, $filter);
    }
}
