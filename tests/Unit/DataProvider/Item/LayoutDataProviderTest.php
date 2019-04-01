<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\DataProvider\Item;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\DataProvider\Item\LayoutDataProvider;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Repository\Layout\LayoutRepository;

class LayoutDataProviderTest extends TestCase
{
    public function test_default_layout_data_provider()
    {
        $layout = new Layout();

        $layoutRepositoryMock = $this->getMockBuilder(LayoutRepository::class)->disableOriginalConstructor()->getMock();

        $layoutRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['default' => true])
            ->willReturn($layout);

        $provider = new LayoutDataProvider($layoutRepositoryMock);
        $this->assertEquals($layout, $provider->getItem(Layout::class, 'default'));
    }
}
