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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Silverback\ApiComponentBundle\Entity\Core\Route;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\DataFixtures\RouteFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RouteRepositoryTest extends KernelTestCase
{
    use FixturesTrait;

    private ?Registry $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine');
        $this->loadFixtures([
            RouteFixtures::class,
        ]);
    }

    public function test_get_default_layout(): void
    {
        $repository = new RouteRepository($this->entityManager);

        $this->assertNull($repository->findOneByIdOrRoute('/does_not_exist'));
        $routeByRoute = $repository->findOneByIdOrRoute('/path');
        $this->assertInstanceOf(Route::class, $routeByRoute);

        $routeById = $repository->findOneByIdOrRoute($routeByRoute->getId());
        $this->assertInstanceOf(Route::class, $routeById);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->getManager()->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
