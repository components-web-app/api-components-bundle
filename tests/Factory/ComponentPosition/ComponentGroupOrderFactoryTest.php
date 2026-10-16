<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\ComponentPosition;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Factory\ComponentPosition\ComponentGroupOrderFactory;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;

class ComponentGroupOrderFactoryTest extends TestCase
{
    public function test_the_order_lists_the_listed_positions_by_sort_value_then_id(): void
    {
        $group = new ComponentGroup();
        (new \ReflectionProperty(ComponentGroup::class, 'id'))->setValue($group, Uuid::fromString('11111111-1111-4111-8111-111111111111'));
        $group->allowedComponents = ['/component/dummy_components'];
        $late = $this->position('dddddddd-0000-4000-8000-000000000000', 9, new DummyComponent());
        $tiedSecond = $this->position('bbbbbbbb-0000-4000-8000-000000000000', 2, new DummyComponent());
        $tiedFirst = $this->position('aaaaaaaa-0000-4000-8000-000000000000', 2, new DummyComponent());
        $hidden = $this->position('cccccccc-0000-4000-8000-000000000000', 0, new RestrictedComponent());

        $order = $this->factory()->create($group, [$late, $tiedSecond, $hidden, $tiedFirst]);

        self::assertSame('11111111-1111-4111-8111-111111111111', $order->id);
        self::assertSame($group, $order->componentGroup);
        self::assertSame([
            '/position/aaaaaaaa-0000-4000-8000-000000000000' => 2,
            '/position/bbbbbbbb-0000-4000-8000-000000000000' => 2,
            '/position/dddddddd-0000-4000-8000-000000000000' => 9,
        ], $order->sortValues);
    }

    public function test_a_group_with_no_positions_has_an_empty_order(): void
    {
        self::assertSame([], $this->factory()->create(new ComponentGroup(), [])->sortValues);
    }

    private function factory(): ComponentGroupOrderFactory
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static function (object|string $resource, int $referenceType = 1, ?Operation $operation = null): string {
                if ($resource instanceof ComponentPosition) {
                    return '/position/' . $resource->getId();
                }

                return DummyComponent::class === $resource ? '/component/dummy_components' : '/component/restricted_components';
            },
        );

        return new ComponentGroupOrderFactory($iriConverter, new ListedComponentPositions($iriConverter));
    }

    private function position(string $id, int $sortValue, AbstractComponent $component): ComponentPosition
    {
        $position = new ComponentPosition();
        (new \ReflectionProperty(ComponentPosition::class, 'id'))->setValue($position, Uuid::fromString($id));
        $position->sortValue = $sortValue;
        $position->component = $component;

        return $position;
    }
}
