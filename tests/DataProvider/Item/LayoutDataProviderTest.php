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

use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\Item\LayoutDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;

class LayoutDataProviderTest extends TestCase
{
    public function test_supports(): void
    {
        $repository = $this->createMock(LayoutRepository::class);
        $provider = new LayoutDataProvider($repository);

        $this->assertTrue($provider->supports(Layout::class));
        $this->assertFalse($provider->supports(__CLASS__));
    }

    public function test_unsupported_id(): void
    {
        $repository = $this->createMock(LayoutRepository::class);
        $repository
            ->expects($this->never())
            ->method('findOneBy');
        $this->expectException(ResourceClassNotSupportedException::class);
        $this->expectExceptionMessage(LayoutDataProvider::class . ' only supports the id `default`');
        $provider = new LayoutDataProvider($repository);
        $provider->getItem(Layout::class, 'abcd');
    }

    public function test_get_default_layout(): void
    {
        $repository = $this->createMock(LayoutRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($layout = new Layout());
        $provider = new LayoutDataProvider($repository);
        $this->assertEquals($layout, $provider->getItem(Layout::class, 'default'));
    }
}
