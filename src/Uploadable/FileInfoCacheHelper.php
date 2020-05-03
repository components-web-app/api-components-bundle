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

namespace Silverback\ApiComponentsBundle\Uploadable;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;

class FileInfoCacheHelper
{
    private EntityManagerInterface $entityManager;
    private ObjectRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(FileInfo::class);
    }

    public function saveCache(FileInfo $fileInfo): void
    {
        $this->entityManager->persist($fileInfo);
        $this->entityManager->flush();
    }

    public function deleteCaches(array $paths, array $filters): void
    {
        foreach ($paths as $path) {
            foreach ($filters as $filter) {
                $metadata = $this->repository->findOneBy([
                    'path' => $path,
                    'filter' => $filter,
                ]);
                if ($metadata) {
                    $this->entityManager->remove($metadata);
                }
            }
        }
        $this->entityManager->flush();
    }

    public function resolveCache(string $path, string $filter): ?FileInfo
    {
        return $this->repository
            ->findOneBy(
                [
                    'path' => $path,
                    'filter' => $filter,
                ]
            );
    }
}
