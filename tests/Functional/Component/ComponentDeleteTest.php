<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Component;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentDeleteTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    public function test_deleting_a_component_removes_the_groups_only_it_owns_with_their_positions_and_components(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::newPage();
        $pageGroup = self::newComponentGroup('page_group');
        $page->addComponentGroup($pageGroup);
        $owner = new DummyComponent();
        $ownerPosition = self::newComponentPosition($pageGroup, $owner, 0);
        $ownedGroup = self::newComponentGroup('owned_group');
        $owner->addComponentGroup($ownedGroup);
        $ownedComponent = new DummyComponent();
        $ownedPosition = self::newComponentPosition($ownedGroup, $ownedComponent, 0);
        $sharedGroup = self::newComponentGroup('shared_group');
        $owner->addComponentGroup($sharedGroup);
        $page->addComponentGroup($sharedGroup);
        $sharedComponent = new DummyComponent();
        $sharedPosition = self::newComponentPosition($sharedGroup, $sharedComponent, 0);
        self::persistAndFlush($page, $pageGroup, $owner, $ownerPosition, $ownedGroup, $ownedComponent, $ownedPosition, $sharedGroup, $sharedComponent, $sharedPosition);
        $iris = array_map(self::iriOf(...), [
            'owner' => $owner,
            'ownedGroup' => $ownedGroup,
            'ownedPosition' => $ownedPosition,
            'ownedComponent' => $ownedComponent,
            'sharedGroup' => $sharedGroup,
            'sharedPosition' => $sharedPosition,
            'sharedComponent' => $sharedComponent,
            'pageGroup' => $pageGroup,
        ]);

        self::requestJsonLd($client, 'DELETE', $iris['owner']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($iris['owner']);
        self::assertResourceNotExists($iris['ownedGroup']);
        self::assertResourceNotExists($iris['ownedPosition']);
        self::assertResourceNotExists($iris['ownedComponent']);
        self::assertResourceExists($iris['sharedGroup']);
        self::assertResourceExists($iris['sharedPosition']);
        self::assertResourceExists($iris['sharedComponent']);
        self::assertResourceExists($iris['pageGroup']);
    }

    public function test_deleting_a_layout_removes_the_groups_only_it_owns(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::newLayout('primary', 'Primary');
        $group = self::newComponentGroup('header');
        $layout->addComponentGroup($group);
        $component = new DummyComponent();
        $position = self::newComponentPosition($group, $component, 0);
        self::persistAndFlush($layout, $group, $component, $position);
        $iris = array_map(self::iriOf(...), ['layout' => $layout, 'group' => $group, 'position' => $position, 'component' => $component]);

        self::requestJsonLd($client, 'DELETE', $iris['layout']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($iris['group']);
        self::assertResourceNotExists($iris['position']);
        self::assertResourceNotExists($iris['component']);
    }
}
