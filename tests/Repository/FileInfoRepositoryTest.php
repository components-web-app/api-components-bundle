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
}
