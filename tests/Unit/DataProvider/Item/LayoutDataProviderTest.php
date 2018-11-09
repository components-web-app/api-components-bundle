<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\DataProvider\Item;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\DataProvider\Item\LayoutDataProvider;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Repository\LayoutRepository;

class LayoutDataProviderTest extends TestCase
{
    /**
     * @throws \ApiPlatform\Core\Exception\ResourceClassNotSupportedException
     */
    public function test_default_layout_data_provider()
    {
        $layout = new Layout();
        $layout->setDefault(true);

        $objectManagerMock = $this->getMockBuilder(ObjectManager::class)->getMock();
        $managerRegistryMock = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $layoutRepositoryMock = $this->getMockBuilder(LayoutRepository::class)->disableOriginalConstructor()->getMock();

        $objectManagerMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Layout::class)
            ->willReturn($layoutRepositoryMock)
        ;

        $managerRegistryMock
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with(Layout::class)
            ->willReturn($objectManagerMock)
        ;

        $layoutRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['default' => true])
            ->willReturn($layout)
        ;

        /** @var ManagerRegistry $managerRegistryMock */
        $provider = new LayoutDataProvider($managerRegistryMock);
        $this->assertEquals($layout, $provider->getItem(Layout::class, 'default'));
    }
}
