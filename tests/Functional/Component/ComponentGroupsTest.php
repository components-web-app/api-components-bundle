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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;

final class ComponentGroupsTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ComponentSiteTrait;

    public function test_i_cannot_add_a_component_collection_without_a_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/_/component_groups', '{"reference": ""}');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
    }

    public function test_i_can_create_a_component_collection_resource(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/_/component_groups', '{"reference": "collection_reference", "location": "collection_location"}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component_group.schema.json');
        self::assertJsonNodeExists('createdAt');
    }

    public function test_when_i_delete_a_component_collection_resource_locations_are_also_deleted_but_components_are_not(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(4);

        self::requestJsonLd($client, 'DELETE', $group['group']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, self::countEntities(DummyComponent::class));
        self::assertSame(0, self::countEntities(ComponentPosition::class));
    }

    public function test_i_can_get_a_component_group_by_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'GET', '/_/component_groups/collection');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', $group['group']);
    }

    public function test_components_are_ordered_by_the_sort_value_of_the_resource_position(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(4);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        foreach ($group['positions'] as $index => $position) {
            self::assertJsonNodeSame(\sprintf('componentPositions[%d]', $index), $position);
        }
    }

    public function test_i_can_add_a_component_collection_to_pages_and_layouts(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::iriOf(self::createPage());
        $layout = self::iriOf(self::createLayout());
        $component = new DummyPublishableComponent();
        self::persistAndFlush($component);
        $component = self::iriOf($component);

        self::requestJsonLd($client, 'POST', '/_/component_groups', [
            'reference' => 'main_body',
            'location' => $component,
            'pages' => [$page],
            'layouts' => [$layout],
            'components' => [$component],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component_group.schema.json');
        self::assertJsonNodeSame('pages[0]', $page);
        self::assertJsonNodeSame('layouts[0]', $layout);
        self::assertJsonNodeSame('location', $component);
        self::assertJsonNodeSame('components[0]', $component);
    }

    public function test_if_a_component_group_is_orphaned_it_should_be_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentGroupIn(page: true, layout: false);

        self::requestJsonLd($client, 'DELETE', $site['page']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($site['group']);
        self::assertResourceNotExists($site['position']);
        self::assertResourceNotExists($site['component']);
    }

    public function test_if_a_component_group_is_not_orphaned_it_should_not_be_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentGroupIn(page: true, layout: true);

        self::requestJsonLd($client, 'DELETE', $site['page']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists($site['group']);
        self::assertResourceExists($site['position']);
        self::assertResourceExists($site['component']);
    }

    public function test_if_a_component_group_is_deleted_orphaned_positions_and_components_should_be_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentGroupIn(page: true, layout: true);

        self::requestJsonLd($client, 'DELETE', $site['group']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($site['group']);
        self::assertResourceNotExists($site['position']);
        self::assertResourceNotExists($site['component']);
    }

    public function test_positions_with_non_allowed_components_are_filtered_from_the_component_group_response(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(1);
        self::setAllowedComponent($group['group'], '/component/dummy_components');
        self::addPositionTo($group['group'], new DummyPublishableComponent(), 999);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentPositions', 1);
        self::assertJsonNodeSame('componentPositions[0]', $group['positions'][0]);
    }

    public function test_linking_an_existing_component_group_to_a_page_via_patch_purges_the_page_from_the_cache(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::iriOf(self::createPage());
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'PATCH', $group['group'], ['pages' => [$page]]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($page);
    }

    public function test_linking_an_existing_component_group_to_a_layout_via_patch_purges_the_layout_from_the_cache(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::iriOf(self::createLayout());
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'PATCH', $group['group'], ['layouts' => [$layout]]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($layout);
    }

    public function test_a_page_data_property_position_is_not_populated_when_the_resolved_component_type_is_not_in_allowed_components(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $publishable = new DummyPublishableComponent();
        $publishable->setPublishedAt(new \DateTime());
        $position = self::createPageDataPropertyPositionInARestrictedGroup('/test-page', 'publishableComponent', $publishable);

        self::requestJsonLd($client, 'GET', $position, null, ['path' => '/test-page']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull(self::lastJson()['component'] ?? null);
    }

    public function test_an_allowed_page_data_property_position_in_a_restricted_group_returns_the_component_for_anonymous_users(): void
    {
        $client = static::createClient();
        $position = self::createPageDataPropertyPositionInARestrictedGroup('/test-page', 'component', new DummyComponent());

        self::requestJsonLd($client, 'GET', $position, null, ['path' => '/test-page']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('component', '/\/component\/dummy_components\/[a-z0-9\-]+/');
    }

    public function test_sending_a_php_class_name_as_allowed_components_is_normalised_to_a_collection_iri(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'PATCH', $group['group'], ['allowedComponents' => [DummyComponent::class]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('allowedComponents[0]', '/component/dummy_components');
    }

    public function test_positions_hidden_by_allowed_components_still_leave_component_positions_as_a_json_list(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::addRestrictedComponentPosition($group['group'], -10);
        self::setAllowedComponent($group['group'], '/component/dummy_components');

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentPositions', 1);
        self::assertJsonNodeSame('componentPositions[0]', $group['positions'][0]);
    }

    /**
     * @return array{page: string, group: string, position: string, component: string}
     */
    private static function createComponentGroupIn(bool $page, bool $layout): array
    {
        $created = self::newComponentGroupWithComponents(1);
        $entities = [$created['group'], ...$created['components'], ...$created['positions']];
        $pageEntity = self::newPage();
        if ($page) {
            $pageEntity->addComponentGroup($created['group']);
            $entities[] = $pageEntity;
        }
        if ($layout) {
            $layoutEntity = self::newLayout();
            $layoutEntity->addComponentGroup($created['group']);
            $entities[] = $layoutEntity;
        }
        self::persistAndFlush(...$entities);

        return [
            'page' => self::iriOf($pageEntity),
            'group' => self::iriOf($created['group']),
            'position' => self::iriOf($created['positions'][0]),
            'component' => self::iriOf($created['components'][0]),
        ];
    }

    private static function createPageDataPropertyPositionInARestrictedGroup(string $path, string $property, AbstractComponent $component): string
    {
        $group = self::newComponentGroup('test');
        $group->allowedComponents = ['/component/dummy_components'];
        $position = new ComponentPosition();
        $position->pageDataProperty = $property;
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $group;
        $position->sortValue = 0;
        $page = self::newPage('test page', true);
        $page->addComponentGroup($group);
        $pageData = new PageDataWithComponent();
        $pageData->{$property} = $component;
        $pageData->page = $page;
        $route = self::newRoute($path)->setPageData($pageData);
        self::persistAndFlush($group, $position, $page, $component, $pageData, $route);

        return self::iriOf($position);
    }
}
