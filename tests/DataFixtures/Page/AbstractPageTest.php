<?php

namespace Silverback\ApiComponentBundle\Tests\DataFixtures\Page;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Factory\Component\HeroFactory;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\Component\Hero;
use Silverback\ApiComponentBundle\Entity\Page;
use Silverback\ApiComponentBundle\Entity\Route;

class AbstractPageTest extends TestCase
{
    private $componentOwner;
    private $abstractPageMock;
    private $componentServiceLocator;
    private $heroComponentMock;
    private $objectManagerProphecy;
    private $heroEntity;
    private $pageEntity;

    public function setUp ()
    {
        $this->heroEntity = new Hero();
        $this->componentOwner = new Page();
        $this->objectManagerProphecy = $this->prophesize(ObjectManager::class);

        $this->heroComponentMock = $this->getMockBuilder(HeroFactory::class)
            ->setConstructorArgs([$this->objectManagerProphecy->reveal()])
            ->getMock()
        ;

        $this->componentServiceLocator = $this->getMockBuilder(ComponentServiceLocator::class)
            ->setConstructorArgs(
                [
                    [
                        HeroFactory::class,
                    ]
                ]
            )
            ->getMock()
        ;

        $this->abstractPageMock = $this->getMockForAbstractClass(AbstractPage::class, [
            $this->componentServiceLocator
        ]);


        $this->pageEntity = $this->abstractPageMock->load($this->objectManagerProphecy->reveal());
    }

    public function test_create_component_methods_called ()
    {
        $this->heroComponentMock
            ->expects($this->once())
            ->method('create')
            ->with($this->componentOwner, null)
            ->will($this->returnValue($this->heroEntity))
        ;

        $this->componentServiceLocator
            ->expects($this->once())
            ->method('get')
            ->with(HeroFactory::class)
            ->will($this->returnValue($this->heroComponentMock))
        ;

        $component = $this->abstractPageMock->createComponent(HeroFactory::class, $this->componentOwner);
        $this->assertInstanceOf(Hero::class, $component);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_redirect_from_before_flush ()
    {
        $this->expectException(\BadMethodCallException::class);

        $redirectFrom = new \ReflectionMethod(AbstractPage::class, 'redirectFrom');
        $redirectFrom->setAccessible(true);
        $redirectFrom->invokeArgs($this->abstractPageMock, [
            new Page()
        ]);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_page_flush_and_redirect ()
    {
        $this->objectManagerProphecy
            ->persist($this->pageEntity)
            ->shouldBeCalled()
        ;
        $this->objectManagerProphecy
            ->flush()
            ->shouldBeCalled()
        ;

        $flush = new \ReflectionMethod(AbstractPage::class, 'flush');
        $flush->setAccessible(true);
        $flush->invoke($this->abstractPageMock);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_redirect_after_flush_but_no_routes ()
    {
        $flush = new \ReflectionMethod(AbstractPage::class, 'flush');
        $flush->setAccessible(true);
        $flush->invoke($this->abstractPageMock);

        $redirectFrom = new \ReflectionMethod(AbstractPage::class, 'redirectFrom');
        $redirectFrom->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $redirectFrom->invokeArgs($this->abstractPageMock, [
            new Page()
        ]);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_redirect_after_flush ()
    {
        $flush = new \ReflectionMethod(AbstractPage::class, 'flush');
        $flush->setAccessible(true);
        $flush->invoke($this->abstractPageMock);

        $redirectFrom = new \ReflectionMethod(AbstractPage::class, 'redirectFrom');
        $redirectFrom->setAccessible(true);

        $route = $this
            ->getMockBuilder(Route::class)
            ->setConstructorArgs([ '/' ])
            ->getMock()
        ;
        $page = new Page();
        $page->addRoute($route);

        $routeRedirect = $this
            ->getMockBuilder(Route::class)
            ->setConstructorArgs([ '/redirect' ])
            ->getMock()
        ;
        $this->pageEntity->addRoute($routeRedirect);

        $routeRedirect
            ->expects($this->once())
            ->method('setRedirect')
            ->with($route)
        ;

        $this->objectManagerProphecy
            ->flush()
            ->shouldBeCalled()
        ;

        $redirectFrom->invokeArgs($this->abstractPageMock, [
            $this->pageEntity
        ]);
    }
}
