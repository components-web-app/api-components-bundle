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

namespace Silverback\ApiComponentsBundle\Tests\Repository\Core;

use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;

class FileInfoRepositoryTest extends AbstractRepositoryTest
{
    private FileInfoRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');

        $this->clearSchema($registry);

        $this->repository = new FileInfoRepository($registry);
    }

    public function test_find_one_by_path_and_filter(): void
    {
        $fileInfoWithFilter = new FileInfo('path', 'mime', 10, 20, 30, 'filter');
        $this->entityManager->persist($fileInfoWithFilter);

        $fileInfoWithNullFilter = new FileInfo('path', 'mime', 10, 20, 30, null);
        $this->entityManager->persist($fileInfoWithNullFilter);

        $this->entityManager->flush();

        $this->assertEquals($fileInfoWithFilter, $this->repository->findOneByPathAndFilter('path', 'filter'));
        $this->assertEquals($fileInfoWithNullFilter, $this->repository->findOneByPathAndFilter('path', null));
        $this->assertNull($this->repository->findOneByPathAndFilter('does_not_exist', null));
    }

    public function test_find_by_paths_and_filters(): void
    {
        $paths = ['path_1', 'path_2'];
        $filters = [null, 'filter_1', 'filter_2'];
        foreach ($paths as $path) {
            foreach ($filters as $filter) {
                $fileInfo = new FileInfo($path, 'mime', 10, 20, 30, $filter);
                $this->entityManager->persist($fileInfo);
            }
        }
        $this->entityManager->flush();

        $this->assertCount(6, $this->repository->findByPathsAndFilters(['path_1', 'path_2'], [null, 'filter_1', 'filter_2']));
        $this->assertCount(3, $this->repository->findByPathsAndFilters(['path_1'], [null, 'filter_1', 'filter_2']));
        $this->assertCount(4, $this->repository->findByPathsAndFilters(['path_1', 'path_2'], ['filter_1', 'filter_2']));
        $this->assertCount(2, $this->repository->findByPathsAndFilters(['path_1', 'path_2'], ['filter_2']));
        $this->assertCount(0, $this->repository->findByPathsAndFilters(['no_path'], [null]));
    }
}
