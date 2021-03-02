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

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\DataProvider\Item\RouteDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

class RouteDataProviderTest extends TestCase
{
    public function test_supports(): void
    {
        $repository = $this->createMock(RouteRepository::class);
        $defaultProvider = $this->createMock(ItemDataProviderInterface::class);
        $provider = new RouteDataProvider($repository, $defaultProvider);

        $this->assertTrue($provider->supports(Route::class));
        $this->assertFalse($provider->supports(__CLASS__));
        $this->assertFalse($provider->supports(Route::class, null, ['ROUTE_DATA_PROVIDER_ALREADY_CALLED' => true]));
    }

    public function test_calls_repository_method(): void
    {
        $repository = $this->createMock(RouteRepository::class);
        $defaultProvider = $this->createMock(ItemDataProviderInterface::class);
        $defaultProvider
            ->expects($this->never())
            ->method('getItem');
        $repository
            ->expects(self::once())
            ->method('findOneByIdOrPath')
            ->with('abcd')
            ->willReturn($route = new Route());

        $provider = new RouteDataProvider($repository, $defaultProvider);
        $this->assertEquals($route, $provider->getItem(Route::class, 'abcd'));
    }

    public function test_calls_default_for_uuid(): void
    {
        $uuid = Uuid::uuid4();
        $repository = $this->createMock(RouteRepository::class);
        $defaultProvider = $this->createMock(ItemDataProviderInterface::class);
        $defaultProvider
            ->expects(self::once())
            ->method('getItem')
            ->with(Route::class, $uuid, 'post', ['blah' => 'abc', 'ROUTE_DATA_PROVIDER_ALREADY_CALLED' => true])
            ->willReturn($route = new Route());
        $repository
            ->expects($this->never())
            ->method('findOneByIdOrPath');

        $provider = new RouteDataProvider($repository, $defaultProvider);
        $this->assertEquals($route, $provider->getItem(Route::class, $uuid, 'post', ['blah' => 'abc']));
    }
}
