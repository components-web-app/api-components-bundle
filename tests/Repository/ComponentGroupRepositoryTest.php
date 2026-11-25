<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Repository;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

final class ComponentGroupRepositoryTest extends OrphanedResourceDatabaseTestCase
{
    public function test_the_groups_restricting_a_components_ui_are_those_holding_it_in_a_position_once_each_by_reference(): void
    {
        $component = new DummyComponent();
        $second = $this->restrictingGroup('b');
        $first = $this->restrictingGroup('a');
        $this->position($second, $component);
        $this->position($second, $component);
        $this->position($first, $component);
        $this->position($this->group('unrestricted'), $component);
        $this->position($this->restrictingGroup('another-component'), new DummyComponent());
        $owned = $this->restrictingGroup('owned');
        $component->addComponentGroup($owned);
        $this->entityManager->flush();

        self::assertSame([$first, $second], (new ComponentGroupRepository($this->registry))->findRestrictingUiOf($component));
    }

    private function restrictingGroup(string $reference): ComponentGroup
    {
        $group = $this->group($reference);
        $group->allowedComponentUi = ['/component/dummy_components' => ['ui' => ['CwaHero']]];

        return $group;
    }
}
