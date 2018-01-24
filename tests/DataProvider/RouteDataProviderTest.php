<?php

namespace Silverback\ApiComponentBundle\Tests\DataProvider;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\DataProvider\Item\RouteDataProvider;
use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Entity\Route;

class RouteDataProviderTest extends TestCase
{
    private $dataProvider;
    private $objectRepositoryProphecy;

    public function setUp()
    {
        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $this->objectRepositoryProphecy = $this->prophesize(ObjectRepository::class);
        $this->dataProvider = new RouteDataProvider($managerRegistryProphecy->reveal());

        $objectManagerProphecy->getRepository(Route::class)->willReturn($this->objectRepositoryProphecy->reveal());
        $managerRegistryProphecy->getManagerForClass(Route::class)->willReturn($objectManagerProphecy->reveal());
    }

    public function test_route_404()
    {
        $this->objectRepositoryProphecy->find(1)->willReturn(null);
        $this->assertEquals(null, $this->dataProvider->getItem(Route::class, 1));
    }

    public function test_route_redirect()
    {
        $redirectRoute = new Route('/redirected', new Page());
        $route = new Route('/', null, $redirectRoute);
        $this->objectRepositoryProphecy->find(1)->willReturn($route);
        $this->assertEquals($route, $this->dataProvider->getItem(Route::class, 1));
    }

    public function test_route_page_stack()
    {
        $pageRoot = new Page();
        $page = new Page();
        $page->setParent($pageRoot);

        $route = new Route('/', $page);
        $this->objectRepositoryProphecy->find(1)->willReturn($route);
        $this->assertEquals(
            [
                $pageRoot,
                $page
            ],
            $this->dataProvider->getItem(Route::class, 1)
        );
    }
}
