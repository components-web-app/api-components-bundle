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

use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;

class LayoutRepositoryTest extends AbstractRepositoryTest
{
    private LayoutRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');

        $this->clearSchema($registry);

        $this->repository = new LayoutRepository($registry);
    }

    public function test_find_layout(): void
    {
        $layout = new Layout();
        $layout->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $layout->reference = 'primary';
        $this->entityManager->persist($layout);

        $this->entityManager->flush();
        $this->assertEquals($layout, $this->repository->find($layout->getId()));
    }
}
