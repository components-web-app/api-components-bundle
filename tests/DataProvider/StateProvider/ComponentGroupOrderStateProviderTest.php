<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupOrderStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Factory\ComponentPosition\ComponentGroupOrderFactory;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;

class ComponentGroupOrderStateProviderTest extends TestCase
{
    public function test_the_order_of_a_group_found_by_id_or_reference(): void
    {
        $group = new ComponentGroup();
        $position = new ComponentPosition();
        $position->sortValue = 4;
        $group->addComponentPosition($position);
        $repository = $this->createMock(ComponentGroupRepository::class);
        $repository->expects(self::once())->method('findOneByIdOrReference')->with('header')->willReturn($group);

        $order = $this->provider($repository)->provide(new Get(), ['id' => 'header']);

        self::assertSame($group, $order?->componentGroup);
        self::assertSame(['/position' => 4], $order->sortValues);
    }

    public function test_a_group_that_does_not_exist_has_no_order(): void
    {
        $repository = $this->createStub(ComponentGroupRepository::class);
        $repository->method('findOneByIdOrReference')->willReturn(null);

        self::assertNull($this->provider($repository)->provide(new Get(), ['id' => 'missing']));
    }

    public function test_an_id_that_is_not_a_string_has_no_order(): void
    {
        $repository = $this->createMock(ComponentGroupRepository::class);
        $repository->expects(self::never())->method('findOneByIdOrReference');

        self::assertNull($this->provider($repository)->provide(new Get(), []));
    }

    private function provider(ComponentGroupRepository $repository): ComponentGroupOrderStateProvider
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/position');

        return new ComponentGroupOrderStateProvider($repository, new ComponentGroupOrderFactory($iriConverter, new ListedComponentPositions($iriConverter)));
    }
}
