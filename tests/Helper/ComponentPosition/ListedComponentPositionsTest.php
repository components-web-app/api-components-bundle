<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentPosition;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;

class ListedComponentPositionsTest extends TestCase
{
    public function test_every_position_is_listed_when_the_group_allows_any_component(): void
    {
        $group = new ComponentGroup();
        $dummy = $this->place($group, new DummyComponent());
        $restricted = $this->place($group, new RestrictedComponent());

        self::assertSame([$dummy, $restricted], $this->helper()->of($group));
    }

    public function test_a_static_position_whose_component_is_not_allowed_is_not_listed(): void
    {
        $group = new ComponentGroup();
        $group->allowedComponents = ['/component/dummy_components'];
        $this->place($group, new RestrictedComponent());
        $dummy = $this->place($group, new DummyComponent());
        $dynamic = new ComponentPosition();
        $dynamic->pageDataProperty = 'component';
        $group->addComponentPosition($dynamic);

        self::assertSame([$dummy, $dynamic], $this->helper()->of($group));
    }

    private function helper(): ListedComponentPositions
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static function (object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null): string {
                self::assertSame(UrlGeneratorInterface::ABS_PATH, $referenceType);
                self::assertInstanceOf(GetCollection::class, $operation);
                self::assertSame($resource, $operation->getClass());

                return DummyComponent::class === $resource ? '/component/dummy_components' : '/component/restricted_components';
            },
        );

        return new ListedComponentPositions($iriConverter);
    }

    private function place(ComponentGroup $group, AbstractComponent $component): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->component = $component;
        $group->addComponentPosition($position);

        return $position;
    }
}
