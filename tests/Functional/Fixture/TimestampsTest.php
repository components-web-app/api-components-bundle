<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedComponent;

final class TimestampsTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;

    public function test_an_entity_given_only_to_persist_and_a_component_held_only_in_a_page_data_field_are_timestamped(): void
    {
        $this->runCommandLine('doctrine:fixtures:load', 'cwa_timestamped');

        $this->assertEntitiesHaveACreatedAndAModifiedDate(1, DummyTimestamped::class);
        $this->assertEntitiesHaveACreatedAndAModifiedDate(1, DummyTimestampedComponent::class);
    }
}
