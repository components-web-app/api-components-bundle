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
use Silverback\ApiComponentBundle\Entity\Core\Route;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RouteRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private RouteRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');
        $this->repository = new RouteRepository($registry);
        $this->entityManager = $registry->getManager();
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function test_get_default_layout(): void
    {
        $route = new Route();
        $route->route = '/path';
        $route->name = 'new_route';
        $this->entityManager->persist($route);
        $this->entityManager->flush();

        $this->assertNull($this->repository->findOneByIdOrRoute('/does_not_exist'));
        $routeByRoute = $this->repository->findOneByIdOrRoute('/path');
        $this->assertInstanceOf(Route::class, $routeByRoute);

        $routeById = $this->repository->findOneByIdOrRoute($routeByRoute->getId());
        $this->assertInstanceOf(Route::class, $routeById);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
