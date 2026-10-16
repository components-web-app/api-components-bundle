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
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupOrderStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Symfony\Component\HttpFoundation\Request;

class ComponentGroupOrderStateProviderTest extends TestCase
{
    public function test_the_order_lists_the_listed_positions_by_sort_value_then_id_and_tags_them(): void
    {
        $group = new ComponentGroup();
        (new \ReflectionProperty(ComponentGroup::class, 'id'))->setValue($group, Uuid::fromString('11111111-1111-4111-8111-111111111111'));
        $group->allowedComponents = ['/component/dummy_components'];
        $this->place($group, 'dddddddd-0000-4000-8000-000000000000', 9, new DummyComponent());
        $this->place($group, 'bbbbbbbb-0000-4000-8000-000000000000', 2, new DummyComponent());
        $this->place($group, 'cccccccc-0000-4000-8000-000000000000', 0, new RestrictedComponent());
        $this->place($group, 'aaaaaaaa-0000-4000-8000-000000000000', 2, new DummyComponent());
        $repository = $this->createMock(ComponentGroupRepository::class);
        $repository->expects(self::once())->method('findOneByIdOrReference')->with('header')->willReturn($group);
        $request = new Request();
        $request->attributes->set('_resources', ['/other' => '/other']);

        $order = $this->provider($repository)->provide(new Get(), ['id' => 'header'], ['request' => $request]);

        self::assertSame('11111111-1111-4111-8111-111111111111', $order?->id);
        self::assertSame('/group', $order->componentGroup);
        $expected = [
            '/position/aaaaaaaa-0000-4000-8000-000000000000' => 2,
            '/position/bbbbbbbb-0000-4000-8000-000000000000' => 2,
            '/position/dddddddd-0000-4000-8000-000000000000' => 9,
        ];
        self::assertSame($expected, $order->sortValues->getArrayCopy());
        self::assertSame(array_keys($expected), $order->componentPositions);
        $tags = array_keys($request->attributes->get('_resources'));
        self::assertSame(['/other', '/group', ...array_keys($expected)], $tags);
    }

    public function test_an_empty_group_has_an_empty_order_without_a_request(): void
    {
        $repository = $this->createStub(ComponentGroupRepository::class);
        $repository->method('findOneByIdOrReference')->willReturn(new ComponentGroup());

        self::assertSame([], $this->provider($repository)->provide(new Get(), ['id' => 'header'])?->sortValues->getArrayCopy());
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
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (object|string $resource): string => match (true) {
            $resource instanceof ComponentPosition => '/position/' . $resource->getId(),
            $resource instanceof ComponentGroup => '/group',
            DummyComponent::class === $resource => '/component/dummy_components',
            default => '/component/restricted_components',
        });

        return new ComponentGroupOrderStateProvider($repository, new ListedComponentPositions($iriConverter), new ComponentPositionOrderer(), $iriConverter);
    }

    private function place(ComponentGroup $group, string $id, int $sortValue, AbstractComponent $component): void
    {
        $position = new ComponentPosition();
        (new \ReflectionProperty(ComponentPosition::class, 'id'))->setValue($position, Uuid::fromString($id));
        $position->sortValue = $sortValue;
        $position->component = $component;
        $group->addComponentPosition($position);
    }
}
