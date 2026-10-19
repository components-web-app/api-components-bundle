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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class DynamicPageTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use SiteTrait;

    private const string DUMMY_COMPONENT_IRI = '/\/component\/dummy_components\/[a-z0-9\-]+/';

    public function test_when_i_get_a_page_data_resource_it_will_be_returned_with_the_components_generated(): void
    {
        $client = self::adminClient();
        $page = self::newPage('test page', true);
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        self::persistAndFlush($page, $pageData);

        self::requestJsonLd($client, 'GET', self::iriOf($pageData));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('_metadata.pageDataMetadata.properties', 2);
        self::assertJsonNodeSame('_metadata.pageDataMetadata.properties[0].property', 'component');
        self::assertJsonNodeSame('_metadata.pageDataMetadata.properties[0].componentShortName', 'DummyComponent');
    }

    public function test_populating_the_component_from_a_page_data_property_with_an_absolute_path_header(): void
    {
        $client = static::createClient();
        $created = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']), headers: ['path' => 'http://example.com/page-data']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
    }

    public function test_populating_the_component_from_a_page_data_property_with_a_relative_path_header(): void
    {
        $client = static::createClient();
        $created = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']), headers: ['path' => '/page-data']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        self::assertNull(self::lastJson()['_metadata']['staticComponent'] ?? null);
        self::assertJsonNodeSame('component', self::iriOf($created['component']));
        self::assertJsonNodeSame('_metadata.isDynamicPosition', true);
        self::assertJsonNodeSame('_metadata.pageDataPath', '/page-data');
    }

    public function test_a_dynamic_position_resolves_its_component_from_a_page_data_iri_in_the_path_header(): void
    {
        $client = static::createClient();
        $created = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']), headers: ['path' => self::iriOf($created['pageData'])]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        self::assertJsonNodeMatches('component', self::DUMMY_COMPONENT_IRI);
        self::assertContains('path', self::getClient()->getResponse()->getVary());
    }

    public function test_a_dynamic_position_resolves_its_component_from_the_iri_of_a_page_data_which_has_no_route(): void
    {
        $client = static::createClient();
        $group = self::newComponentGroup('dynamic parent group');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $parentTemplate = self::newPage('dynamic parent template', true);
        $parentTemplate->addComponentGroup($group);
        $component = new DummyComponent();
        $parentPageData = new PageDataWithComponent();
        $parentPageData->component = $component;
        $parentPageData->page = $parentTemplate;
        $childPage = self::newPage('dynamic routed child');
        $childPage->setParentPageData($parentPageData);
        $route = self::newRoute('/child-path')->setPage($childPage);
        self::persistAndFlush($component, $group, $position, $parentTemplate, $parentPageData, $childPage, $route);

        self::requestJsonLd($client, 'GET', self::iriOf($position), headers: ['path' => self::iriOf($parentPageData)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('component', self::DUMMY_COMPONENT_IRI);
        self::assertContains('path', self::getClient()->getResponse()->getVary());
    }

    public function test_when_a_dynamic_component_is_deleted_related_component_positions_should_be_purged_from_the_cache(): void
    {
        $client = self::adminClient();
        $created = self::createDummyComponentInPageData(new DummyComponent(), true, true);

        self::requestJsonLd($client, 'DELETE', self::iriOf($created['component']));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists(self::iriOf($created['position']));
        self::assertCacheTagPurged(self::iriOf($created['pageData']));
        self::assertCacheTagPurged(self::iriOf($created['component']));
        self::assertCacheTagPurged(self::iriOf($created['position']));
    }

    public function test_when_a_dynamic_component_is_deleted_and_exists_only_in_page_data_the_dynamic_position_should_be_cleared_from_the_cache(): void
    {
        $client = self::adminClient();
        $created = self::createDummyComponentInPageData(new DummyComponent(), true, false);

        self::requestJsonLd($client, 'DELETE', self::iriOf($created['component']));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists(self::iriOf($created['position']));
        self::assertCacheTagPurged(self::iriOf($created['pageData']));
        self::assertCacheTagPurged(self::iriOf($created['component']));
        self::assertCacheTagPurged(self::iriOf($created['position']));
    }

    public function test_when_a_dynamic_component_is_added_related_component_positions_should_be_purged_from_the_cache(): void
    {
        $client = self::adminClient();
        $created = self::createDummyComponentInPageData(new DummyComponent(), false, true);

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['pageData']), \sprintf('{ "%s": "%s" }', 'component', self::iriOf($created['component'])));

        self::assertResponseStatusCodeSame(200);
        self::assertResourceExists(self::iriOf($created['position']));
        self::assertCacheTagPurged(self::iriOf($created['position']));
    }

    public function test_a_static_position_names_its_component_as_the_static_component_and_does_not_vary_on_the_path_header(): void
    {
        $client = static::createClient();
        $created = self::createComponentGroupWithComponents(1);
        $componentIri = self::iriOf($created['components'][0]);

        self::requestJsonLd($client, 'GET', self::iriOf($created['positions'][0]), null, ['path' => '/page-data']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', $componentIri);
        self::assertJsonNodeSame('_metadata.staticComponent', $componentIri);
        self::assertJsonNodeSame('_metadata.isDynamicPosition', false);
        self::assertNotContains('path', self::getClient()->getResponse()->getVary());
    }

    public function test_an_admin_can_fetch_a_page_data_property_position_without_a_path_header_and_the_component_slot_is_not_resolved(): void
    {
        $client = self::adminClient();
        $created = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('pageDataProperty', 'component');
        self::assertJsonNodeSame('pageDataClass', PageDataWithComponent::class);
        self::assertNull(self::lastJson()['component'] ?? null);
        self::assertJsonNodeSame('_metadata.isDynamicPosition', true);
    }

    public function test_fetching_a_page_data_property_position_with_a_path_that_has_no_matching_page_data_returns_the_position_without_a_component(): void
    {
        $client = self::adminClient();
        $created = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']), headers: ['path' => '/unknown-path']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('pageDataProperty', 'component');
        self::assertJsonNodeSame('pageDataClass', PageDataWithComponent::class);
        self::assertNull(self::lastJson()['component'] ?? null);
    }

    public function test_a_published_page_data_property_component_is_returned_for_anonymous_users(): void
    {
        $client = static::createClient();
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime());
        $position = self::createPublishablePageDataPosition($component);

        self::requestJsonLd($client, 'GET', self::iriOf($position), headers: ['path' => '/page-data']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('component', '/\/component\/dummy_publishable_components\/[a-z0-9\-]+/');
    }

    public function test_a_draft_page_data_property_component_is_not_returned_for_anonymous_users(): void
    {
        $client = static::createClient();
        $position = self::createPublishablePageDataPosition(new DummyPublishableComponent());

        self::requestJsonLd($client, 'GET', self::iriOf($position), headers: ['path' => '/page-data']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull(self::lastJson()['component'] ?? null);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    /**
     * @return array{component: AbstractComponent, pageData: PageDataWithComponent, position: ComponentPosition}
     */
    private static function createDummyComponentInPageData(DummyComponent $component, bool $setPageData, bool $inPosition): array
    {
        self::persistAndFlush($component);
        $component = self::getEntityManager()->getReference(DummyComponent::class, $component->getId());
        $group = self::newComponentGroup('test');
        $page = self::newPage('test page', true);
        $pageData = new PageDataWithComponent();
        if ($setPageData) {
            $pageData->component = $component;
        }
        $pageData->page = $page;
        $position = new ComponentPosition();
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        if ($inPosition) {
            $position->component = $component;
        }
        $position->componentGroup = $group;
        $position->sortValue = 0;
        self::persistAndFlush($group, $page, $pageData, $position);

        return ['component' => $component, 'pageData' => $pageData, 'position' => $position];
    }

    private static function createPublishablePageDataPosition(DummyPublishableComponent $component): ComponentPosition
    {
        $group = self::newComponentGroup('test');
        $position = new ComponentPosition();
        $position->pageDataProperty = 'publishableComponent';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = self::newPage('test page', true);
        $page->addComponentGroup($group);
        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $component;
        $pageData->page = $page;
        $route = self::newRoute('/page-data')->setPageData($pageData);
        self::persistAndFlush($group, $position, $page, $component, $pageData, $route);

        return $position;
    }
}
