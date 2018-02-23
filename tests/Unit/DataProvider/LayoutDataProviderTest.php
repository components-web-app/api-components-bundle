<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\DataProvider;

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
    public function test_layout_data_provider()
    {
        $layout = new Layout();
        $layout->setDefault(true);

        $objectManagerProphecy = $this->prophesize(ObjectManager::class);
        $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
        $layoutRepositoryProphecy = $this->prophesize(LayoutRepository::class);

        $objectManagerProphecy->getRepository(Layout::class)->willReturn($layoutRepositoryProphecy->reveal());
        $managerRegistryProphecy->getManagerForClass(Layout::class)->willReturn($objectManagerProphecy->reveal());
        $layoutRepositoryProphecy->findOneBy(['default' => true])->shouldBeCalled()->willReturn($layout);

        $provider = new LayoutDataProvider($managerRegistryProphecy->reveal());
        $this->assertEquals($layout, $provider->getItem(Layout::class, 'default'));
    }
}
