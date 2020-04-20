<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Repository\Core;

use Silverback\ApiComponentBundle\Entity\Core\Layout;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Tests\Repository\AbstractRepositoryTest;

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

    public function test_get_default_layout_does_not_exist(): void
    {
        $this->assertNull($this->repository->findDefault());
    }

    public function test_get_default_layout(): void
    {
        $layout = new Layout();
        $layout->default = false;
        $this->entityManager->persist($layout);

        $defaultLayout = new Layout();
        $defaultLayout->default = true;
        $this->entityManager->persist($defaultLayout);

        $this->entityManager->flush();
        $this->assertEquals($defaultLayout, $this->repository->findDefault());
    }
}
