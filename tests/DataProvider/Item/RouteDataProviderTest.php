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

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\Item;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\Item\RouteDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

class RouteDataProviderTest extends TestCase
{
    public function test_supports(): void
    {
        $repository = $this->createMock(RouteRepository::class);
        $provider = new RouteDataProvider($repository);

        $this->assertTrue($provider->supports(Route::class));
        $this->assertFalse($provider->supports(__CLASS__));
    }

    public function test_calls_repository_method()
    {
        $repository = $this->createMock(RouteRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneByIdOrRoute')
            ->with('abcd')
            ->willReturn($route = new Route());

        $provider = new RouteDataProvider($repository);
        $this->assertEquals($route, $provider->getItem(Route::class, 'abcd'));
    }
}
