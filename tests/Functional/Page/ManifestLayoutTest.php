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

final class ManifestLayoutTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use ManifestTrait;
    use PageSiteTrait;

    public function test_the_manifest_lists_the_layouts_component_groups_positions_and_components_at_the_pages_depth(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createNestedPages('/conference/programme', '/conference');
        $layout = self::usePagesWithLayoutHoldingAComponent([$site['page']]);

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(1, self::iriOf($layout['layout']));
        self::assertManifestDepthContains(1, self::iriOf($layout['group']));
        self::assertManifestDepthContains(1, self::iriOf($layout['position']));
        self::assertManifestDepthContains(1, self::iriOf($layout['component']));
        self::assertManifestDepthNotContains(0, self::iriOf($layout['group']));
    }

    public function test_a_layout_shared_by_a_parent_and_child_page_is_listed_once_at_the_shallowest_depth(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createNestedPages('/conference/programme', '/conference');
        $layout = self::usePagesWithLayoutHoldingAComponent([$site['parentPage'], $site['page']]);

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        foreach (['layout', 'group', 'position', 'component'] as $resource) {
            self::assertManifestDepthContains(0, self::iriOf($layout[$resource]));
        }
        foreach (['layout', 'group', 'position', 'component'] as $resource) {
            self::assertManifestDepthNotContains(1, self::iriOf($layout[$resource]));
        }
    }

    public function test_a_layouts_own_response_still_lists_its_component_groups_as_iris(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createNestedPages('/conference/programme', '/conference');
        $layout = self::usePagesWithLayoutHoldingAComponent([$site['page']]);

        self::requestJsonLd($client, 'GET', self::iriOf($layout['layout']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentGroups[0]', self::iriOf($layout['group']));
    }
}
