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

use ApiPlatform\Test\Client;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class ManifestCacheTagsTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ManifestTrait;
    use SiteTrait;

    public function test_a_manifest_response_carries_a_grouping_key_for_the_page_it_renders_and_none_of_its_members(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($site['page'])));
        self::assertResponseLacksCacheTag(self::iriOf($site['route']));
        self::assertResponseLacksCacheTag(self::iriOf($site['layout']));
        self::assertResponseLacksCacheTag(self::iriOf($site['group']));
        self::assertResponseLacksCacheTag(self::iriOf($site['component']));
    }

    public function test_a_manifest_addressed_by_uuid_carries_the_same_grouping_key_as_one_addressed_by_route_path(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_a_nested_manifest_carries_a_grouping_key_for_every_rendering_depth(): void
    {
        $client = static::createClient();
        $parentPage = self::newPage('parent page', true);
        $parentPageData = new PageData();
        $parentPageData->page = $parentPage;
        $parentRoute = self::newRoute('/conference')->setPageData($parentPageData);
        $childPage = self::newPage('child page', true);
        $childPageData = new PageData();
        $childPageData->page = $childPage;
        $childPageData->setParentPageData($parentPageData);
        $childRoute = self::newRoute('/conference/programme')->setPageData($childPageData);
        self::persistAndFlush($parentPage, $parentPageData, $parentRoute, $childPage, $childPageData, $childRoute);

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//conference/programme');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($childPageData)));
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($parentPageData)));
    }

    public function test_a_page_data_manifest_carries_a_grouping_key_for_its_template_page_as_well_as_its_own(): void
    {
        $client = static::createClient();
        $created = self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($created['pageData'])));
        self::assertResponseHasCacheTag(self::manifestCacheTag(self::iriOf($created['page'])));
    }

    public function test_editing_a_component_group_inside_a_template_page_purges_the_manifest_of_the_page_data_built_from_it(): void
    {
        $client = self::adminClient();
        $created = self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['group']), ['location' => 'new-location']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($created['page'])));
    }

    public function test_an_ordinary_resource_response_is_still_tagged_with_its_own_iri(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'GET', self::iriOf($site['group']));

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasCacheTag(self::iriOf($site['group']));
    }

    public function test_adding_a_component_to_a_group_purges_the_manifest_of_the_page_the_group_is_in(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => self::iriOf($site['group']), 'component' => self::iriOf($component)]);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_adding_a_position_to_a_layouts_component_group_purges_the_manifest_of_a_page_using_the_layout(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $layoutGroup = self::usePageWithLayoutHoldingAComponent($site['page']);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => self::iriOf($layoutGroup), 'component' => self::iriOf($component)]);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_adding_a_position_to_a_components_own_component_group_purges_the_manifest_of_the_page_the_component_is_in(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');
        $nestedGroup = self::giveComponentAGroupHoldingAComponent($site['component'], 'nested');
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => self::iriOf($nestedGroup), 'component' => self::iriOf($component)]);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_editing_a_components_content_does_not_purge_the_manifest_of_the_page_it_is_in(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['component']), ['uiComponent' => 'AnotherTestComponent']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::iriOf($site['component']));
        self::assertCacheTagNotPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_editing_a_page_purges_its_own_manifest(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['page']), ['title' => 'New title']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_editing_a_layout_purges_the_manifest_of_every_page_built_from_it(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['layout']), ['uiComponent' => 'CwaLayoutUpdated']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    public function test_editing_a_route_purges_the_manifest_of_the_page_it_publishes(): void
    {
        $client = self::adminClient();
        $site = self::createRoutedPageWithComponent('/my-route');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['path' => '/another-path']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($site['page'])));
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function createDummyComponent(): DummyComponent
    {
        $component = new DummyComponent();
        self::persistAndFlush($component);

        return $component;
    }

    private static function usePageWithLayoutHoldingAComponent(Page $page): ComponentGroup
    {
        $layout = self::newLayout('shared-layout');
        self::persistAndFlush($layout);
        $group = self::createComponentGroupWithComponents(1, 'layout_top')['group'];
        $manager = self::getEntityManager();
        $layout = $manager->find($layout::class, $layout->getId());
        $layout->addComponentGroup($manager->find(ComponentGroup::class, $group->getId()));
        $manager->find(Page::class, $page->getId())->layout = $layout;
        $manager->flush();
        self::rebootKernel();

        return $group;
    }

    private static function giveComponentAGroupHoldingAComponent(AbstractComponent $component, string $reference): ComponentGroup
    {
        $group = self::createComponentGroupWithComponents(1, $reference)['group'];
        $manager = self::getEntityManager();
        $manager->find($component::class, $component->getId())->addComponentGroup($manager->find(ComponentGroup::class, $group->getId()));
        $manager->flush();
        self::rebootKernel();

        return $group;
    }
}
