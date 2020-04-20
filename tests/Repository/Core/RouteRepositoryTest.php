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

use Silverback\ApiComponentBundle\Entity\Core\Route;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentBundle\Tests\Repository\AbstractRepositoryTest;

class RouteRepositoryTest extends AbstractRepositoryTest
{
    private RouteRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $registry = $kernel->getContainer()->get('doctrine');

        $this->clearSchema($registry);

        $this->repository = new RouteRepository($registry);
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
}
