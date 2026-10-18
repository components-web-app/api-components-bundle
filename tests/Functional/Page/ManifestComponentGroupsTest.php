<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class ManifestComponentGroupsTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ManifestTrait;
    use PageSiteTrait;

    public function test_the_manifest_lists_a_components_own_component_groups_positions_and_components(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $nested = self::giveComponentAGroupHoldingAComponent($site['component'], 'nested');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(0, self::iriOf($site['component']));
        self::assertManifestDepthContains(0, self::iriOf($nested['group']));
        self::assertManifestDepthContains(0, self::iriOf($nested['positions'][0]));
        self::assertManifestDepthContains(0, self::iriOf($nested['components'][0]));
    }

    public function test_the_manifest_follows_component_groups_nested_more_than_one_level_deep(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $nested = self::giveComponentAGroupHoldingAComponent($site['component'], 'nested');
        $deeper = self::giveComponentAGroupHoldingAComponent($nested['components'][0], 'deeper');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(0, self::iriOf($deeper['group']));
        self::assertManifestDepthContains(0, self::iriOf($deeper['positions'][0]));
        self::assertManifestDepthContains(0, self::iriOf($deeper['components'][0]));
    }

    public function test_a_components_groups_on_a_child_page_are_listed_at_the_childs_depth_only(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createNestedPages('/conference/programme', '/conference');
        $child = self::givePageAGroupHoldingAComponent($site['page'], 'child');
        $nested = self::giveComponentAGroupHoldingAComponent($child['components'][0], 'nested');

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(1, self::iriOf($nested['group']));
        self::assertManifestDepthContains(1, self::iriOf($nested['positions'][0]));
        self::assertManifestDepthContains(1, self::iriOf($nested['components'][0]));
        self::assertManifestDepthNotContains(0, self::iriOf($nested['group']));
        self::assertManifestDepthNotContains(0, self::iriOf($nested['components'][0]));
    }

    public function test_a_component_placed_inside_its_own_component_group_does_not_stop_the_manifest(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $loop = self::giveComponentAGroupHoldingItself($site['component'], 'loop');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(0, self::iriOf($loop['group']));
        self::assertManifestDepthContains(0, self::iriOf($loop['position']));
    }

    public function test_a_components_own_response_still_lists_its_component_groups_as_iris_not_embedded(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $nested = self::giveComponentAGroupHoldingAComponent($site['component'], 'nested');

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentGroups[0]', self::iriOf($nested['group']));
    }
}
