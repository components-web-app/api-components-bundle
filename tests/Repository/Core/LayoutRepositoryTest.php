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
use Silverback\ApiComponentBundle\Entity\Core\Layout;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\DataFixtures\LayoutFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LayoutRepositoryTest extends KernelTestCase
{
    use FixturesTrait;

    private ?Registry $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()->get('doctrine');

        $this->loadFixtures([
            LayoutFixtures::class,
        ]);
    }

    public function test_get_default_layout(): void
    {
        $repository = new LayoutRepository($this->entityManager);

        $this->assertInstanceOf(Layout::class, $repository->findDefault());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->getManager()->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
