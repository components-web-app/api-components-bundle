<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Route;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\AbstractFactory;

class RouteFactoryTest extends AbstractFactory
{
    /** @var RouteFactory */
    protected $factory;
    /**
     * @var MockObject|SlugifyInterface
     */
    private $slugifyMock;

    public function getConstructorArgs(): array
    {
        $args = parent::getConstructorArgs();
        $this->slugifyMock = $this->getMockBuilder(SlugifyInterface::class)->getMock();
        $args[] = $this->slugifyMock;
        return $args;
    }

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = RouteFactory::class;
        $this->testOps = [
            'route' => '/dummy-route',
            'content' => $this->getMockForAbstractClass(AbstractContent::class),
            'redirect' => $this->getMockBuilder(Route::class)->getMock()
        ];
        parent::setUp();
    }

    private function expectRepoCalls(): void
    {
        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $repository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null)
        ;
        $this->objectManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Route::class)
            ->willReturn($repository)
        ;
    }

    public function test_shallow_createFromRouteAwareInterface(): void
    {
        $this->expectRepoCalls();
        $routes = ['test-route'];
        /** @var MockObject|Page $routeAwareInterfaceMock */
        $routeAwareInterfaceMock = $this->getMockBuilder(Page::class)->getMock();
        $routeAwareInterfaceMock
            ->expects($this->once())
            ->method('getDefaultRoute')
            ->willReturn($routes[0])
        ;
        $routeAwareInterfaceMock
            ->expects($this->once())
            ->method('getParentRoute')
            ->willReturn(null)
        ;
        $this->slugifyMock
            ->expects($this->once())
            ->method('slugify')
            ->with($routes[0])
            ->willReturn($routes[0])
        ;
        $this->assertEquals('/' . implode('/', $routes), $this->factory->createFromRouteAwareEntity($routeAwareInterfaceMock)->getRoute());
    }

    public function test_deep_createFromRouteAwareInterface(): void
    {
        $this->expectRepoCalls();
        $routes = ['/parent', 'child'];

        /** @var MockObject|Route $routeAwareInterfaceMock */
        $routeAwareInterfaceParentMock = $this->getMockBuilder(Route::class)->getMock();
        $routeAwareInterfaceParentMock
            ->expects($this->once())
            ->method('getRoute')
            ->willReturn($routes[0])
        ;

        /** @var MockObject|Page $routeAwareInterfaceMock */
        $routeAwareInterfaceMock = $this->getMockBuilder(Page::class)->getMock();
        $routeAwareInterfaceMock
            ->expects($this->once())
            ->method('getDefaultRoute')
            ->willReturn($routes[1])
        ;
        $routeAwareInterfaceMock
            ->expects($this->once())
            ->method('getParentRoute')
            ->willReturn($routeAwareInterfaceParentMock)
        ;
        $this->slugifyMock
            ->expects($this->once())
            ->method('slugify')
            ->withConsecutive([$routes[1]], [$routes[0]])
            ->willReturnOnConsecutiveCalls($routes[1],$routes[0])
        ;
        $this->assertEquals(implode('/', $routes), $this->factory->createFromRouteAwareEntity($routeAwareInterfaceMock)->getRoute());
    }
}
