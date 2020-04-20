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

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Core\Layout;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LayoutRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private LayoutRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');
        $this->repository = new LayoutRepository($registry);
        $this->entityManager = $registry->getManager();
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function test_get_default_layout_does_not_exist(): void
    {
        $this->assertNull($this->repository->findDefault());
    }

    public function test_get_default_layout(): void
    {
        $layout = new Layout();
        $layout->default = true;
        $this->entityManager->persist($layout);
        $this->entityManager->flush();
        $this->assertInstanceOf(Layout::class, $this->repository->findDefault());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
