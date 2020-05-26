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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;

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
        $route->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $route->setPath('/path')->setName('new_route');
        $this->entityManager->persist($route);
        $this->entityManager->flush();

        $this->assertNull($this->repository->findOneByIdOrPath('/does_not_exist'));
        $routeByRoute = $this->repository->findOneByIdOrPath('/path');
        $this->assertInstanceOf(Route::class, $routeByRoute);

        $routeById = $this->repository->findOneByIdOrPath((string) $routeByRoute->getId());
        $this->assertInstanceOf(Route::class, $routeById);
    }
}
