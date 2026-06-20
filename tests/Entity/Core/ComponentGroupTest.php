<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Entity\Core;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;

#[\PHPUnit\Framework\Attributes\CoversClass(ComponentGroup::class)]
class ComponentGroupTest extends TestCase
{
    public function test_add_allowed_component_initialises_array_when_null(): void
    {
        $group = new ComponentGroup();
        // allowedComponents starts null
        self::assertNull($group->allowedComponents);

        $group->addAllowedComponent('/_/some_components/123');

        self::assertSame(['/_/some_components/123'], $group->allowedComponents);
    }

    public function test_add_allowed_component_appends_to_existing_array(): void
    {
        $group = new ComponentGroup();
        $group->addAllowedComponent('/_/some_components/1');
        $group->addAllowedComponent('/_/some_components/2');

        self::assertSame(['/_/some_components/1', '/_/some_components/2'], $group->allowedComponents);
    }

    public function test_set_allowed_components_replaces_array(): void
    {
        $group = new ComponentGroup();
        $group->addAllowedComponent('/_/some_components/1');
        $group->setAllowedComponents(['/_/some_components/new']);

        self::assertSame(['/_/some_components/new'], $group->allowedComponents);
    }

    public function test_set_allowed_components_null_clears_array(): void
    {
        $group = new ComponentGroup();
        $group->addAllowedComponent('/_/some_components/1');
        $group->setAllowedComponents(null);

        self::assertNull($group->allowedComponents);
    }
}
