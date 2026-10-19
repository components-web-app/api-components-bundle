<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Route;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class RouteTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use ManifestTrait;
    use RouteSiteTrait;

    public function test_i_can_create_a_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $page = self::createPage();
        $route = self::createRouteWithPage('/other');

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/contact', 'name' => 'contact-page', 'page' => self::iriOf($page), 'redirect' => self::iriOf($route)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('route.schema.json');
    }

    public function test_i_cannot_create_a_route_anonymously(): void
    {
        $client = static::createClient();
        $page = self::createPage();
        $route = self::createRouteWithPage('/other');

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/contact', 'name' => 'contact-page', 'page' => self::iriOf($page), 'redirect' => self::iriOf($route)]);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_route_cannot_be_created_without_a_page_page_data_or_redirect(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createPage();
        self::createRouteWithPage('/other');

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/contact', 'name' => 'contact-page', 'page' => null, 'redirect' => null]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertJsonNodeSame('violations[0].message', 'Please specify either page, pageData or redirect.');
    }

    public function test_a_route_path_with_a_format_extension_resolves_to_the_correct_route(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/contact');

        self::requestJsonLd($client, 'GET', '/_/routes//contact.json');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/contact');
    }

    public function test_a_route_created_without_a_leading_slash_is_automatically_prefixed(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $page = self::createPage();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => 'contact', 'name' => 'contact-page', 'page' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('path', '/contact');
    }

    public function test_i_can_delete_a_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $route = self::createRouteWithPage('/deletable');

        self::requestJsonLd($client, 'DELETE', self::iriOf($route));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_i_can_delete_a_route_which_has_a_redirect(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/contact', '/contact-new');
        $iris = array_map(self::iriOf(...), [$site['final'], $site['route'], $site['middle']]);

        self::requestJsonLd($client, 'DELETE', $iris[0]);

        self::assertResponseStatusCodeSame(204);
        foreach ($iris as $iri) {
            self::assertResourceNotExists($iri);
        }
    }

    public function test_a_route_will_output_the_nested_redirect_routes_and_data_for_the_redirected_page(): void
    {
        $client = static::createClient();
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/contact', '/contact-new');

        self::requestJsonLd($client, 'GET', '/_/routes//contact');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('route.schema.json');
        self::assertJsonNodeSame('redirectPath', '/contact-new');
        self::assertJsonNodeSame('page', self::iriOf($site['page']));
    }

    public function test_a_route_with_its_own_page_and_a_redirect_still_returns_the_page_iri(): void
    {
        $client = static::createClient();
        $site = self::createRouteWithPageAndRedirect('/parent', '/child');

        self::requestJsonLd($client, 'GET', '/_/routes//parent');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('page', self::iriOf($site['page']));
    }

    public function test_a_route_with_its_own_page_data_and_a_redirect_still_returns_the_page_data_iri(): void
    {
        $client = static::createClient();
        $site = self::createRouteWithPageDataAndRedirect('/parent', '/child');

        self::requestJsonLd($client, 'GET', '/_/routes//parent');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('pageData', self::iriOf($site['pageData']));
    }

    public function test_i_can_automatically_generate_a_route_from_a_page_data_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute(null);
        self::setPageDataTitle($site['pageData'], 'Programme');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('route.schema.json');
        self::assertJsonNodeSame('path', '/programme');
        self::assertJsonNodeSame('name', 'programme');
    }

    public function test_a_route_is_not_generated_for_page_data_with_no_title(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute(null);

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'title');
        self::assertJsonNodeSame('violations[0].message', 'A title is needed to generate a route.');
        self::assertRouteCount(0);
    }

    public function test_a_route_is_not_generated_for_a_page_with_no_title(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $page = self::createPage('untitled');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['page' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'title');
        self::assertJsonNodeSame('violations[0].message', 'A title is needed to generate a route.');
        self::assertRouteCount(0);
    }

    public function test_a_route_is_not_generated_for_page_data_whose_existing_route_would_become_a_redirect_when_it_has_no_title(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute('/original');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'title');
        self::assertRouteCount(1);
        self::assertRouteDoesNotRedirect('/original');
    }

    public function test_i_generate_a_route_for_page_data_with_a_pre_existing_route_and_the_original_route_changes_to_a_redirect(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute('/original');
        self::setPageDataTitle($site['pageData'], 'Programme');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('route.schema.json');
        self::assertRouteRedirectsTo('/original', '/programme');
    }

    public function test_a_route_cannot_be_generated_for_a_nested_page_whose_parent_page_has_no_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['page' => self::iriOf($site['childPage'])]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('parent page has no route', self::jsonNode('detail'));
        self::assertRouteCount(0);
    }

    public function test_a_route_generated_for_a_nested_page_whose_parent_page_has_a_route_is_prefixed_with_the_parent_path(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setPageDataTitle($site['pageData'], 'Schedule');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('path', '/conference/schedule');
    }

    public function test_a_route_is_not_generated_when_the_parent_path_and_the_title_slug_together_are_longer_than_the_path_column(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/' . str_repeat('a', 242));
        self::setPageDataTitle($site['pageData'], 'Speaker List');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'path');
        self::assertRouteCount(2);
        self::assertRouteDoesNotRedirect('/conference/programme');
    }

    public function test_a_route_is_generated_when_the_parent_path_and_the_title_slug_together_fill_the_path_column(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $parentPath = '/' . str_repeat('a', 241);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', $parentPath);
        self::setPageDataTitle($site['pageData'], 'Speaker List');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('path', $parentPath . '/speaker-list');
        self::assertRouteRedirectsTo('/conference/programme', $parentPath . '/speaker-list');
    }

    public function test_a_route_can_still_be_created_explicitly_for_a_nested_page_whose_parent_page_has_no_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();

        self::requestJsonLd($client, 'POST', '/_/routes', ['path' => '/2027', 'name' => 'conference-2027', 'page' => self::iriOf($site['childPage'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('path', '/2027');
    }

    public function test_i_update_a_route_path_and_a_new_redirect_is_created(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createPageDataWithRoute('/original');

        self::requestJsonLd($client, 'PATCH', '/_/routes//original', ['path' => '/new']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('route.schema.json');
        self::assertRouteRedirectsTo('/original', '/new');
    }

    public function test_patch_a_route_path_with_cascade_child_paths_updates_child_routes_and_creates_redirects(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['path' => '/new-conference', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/new-conference');
        self::assertRouteRedirectsTo('/conference', '/new-conference');
        self::assertRouteRedirectsTo('/conference/programme', '/new-conference/programme');
    }

    public function test_patch_a_route_path_without_cascade_child_paths_does_not_cascade_to_children(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['path' => '/new-conference']);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteDoesNotRedirect('/conference/programme');
    }

    public function test_patch_with_cascade_child_paths_but_path_unchanged_causes_no_cascade(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['path' => '/conference', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteDoesNotRedirect('/conference/programme');
    }

    public function test_patch_with_cascade_child_paths_ignores_children_with_diverged_route_paths(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/talks', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['path' => '/new-conference', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteDoesNotRedirect('/talks');
    }

    public function test_get_a_routes_children_returns_recursive_tree_with_path_and_iri_per_node(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentRoute']) . '/children');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('children', 1);
        self::assertJsonNodeSame('children[0].route', '/_/routes//conference/programme');
        self::assertJsonNodeSame('children[0].path', '/conference/programme');
        self::assertJsonNodeCount('children[0].children', 0);
    }

    public function test_get_children_for_a_leaf_route_returns_an_empty_children_array(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', self::iriOf($site['route']) . '/children');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('children', 0);
    }

    public function test_get_a_routes_children_includes_routed_descendants_of_a_child_page_that_has_no_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinAnUnroutedPageWithinRoute('/conference/2027/programme', '/conference');

        self::requestJsonLd($client, 'GET', self::iriOf($site['grandparentRoute']) . '/children');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('children', 1);
        self::assertJsonNodeSame('children[0].route', '/_/routes//conference/2027/programme');
        self::assertJsonNodeSame('children[0].path', '/conference/2027/programme');
        self::assertJsonNodeCount('children[0].children', 0);
    }

    public function test_patch_with_cascade_child_paths_updates_routed_descendants_of_a_child_page_that_has_no_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinAnUnroutedPageWithinRoute('/conference/2027/programme', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['grandparentRoute']), ['path' => '/new-conference', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/conference', '/new-conference');
        self::assertRouteRedirectsTo('/conference/2027/programme', '/new-conference/2027/programme');
    }

    public function test_patch_with_cascade_child_paths_is_refused_and_changes_nothing_when_a_descendant_path_would_become_longer_than_the_path_column(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $childPath = '/conference/2027/' . str_repeat('a', 233);
        $site = self::createPageDataNestedWithinTwoRoutes($childPath, '/conference/2027', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['grandparentRoute']), ['path' => '/new-conference-site', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'path');
        self::assertStringContainsString($childPath, self::jsonNode('violations[0].message'));
        self::assertRouteCount(3);
        self::assertRouteDoesNotRedirect('/conference');
        self::assertRouteDoesNotRedirect('/conference/2027');
        self::assertRouteDoesNotRedirect($childPath);
    }

    public function test_patch_with_cascade_child_paths_succeeds_when_a_descendant_path_fills_the_path_column(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $characters = str_repeat('a', 233);
        $site = self::createPageDataNestedWithinTwoRoutes('/conference/2027/' . $characters, '/conference/2027', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['grandparentRoute']), ['path' => '/new-conference1', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/conference/2027', '/new-conference1/2027');
        self::assertRouteRedirectsTo('/conference/2027/' . $characters, '/new-conference1/2027/' . $characters);
    }

    public function test_renaming_a_route_whose_path_fills_the_path_column_and_is_still_its_name_gives_the_redirect_a_name_that_fits_the_name_column(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $path = '/' . str_repeat('a', 254);
        $site = self::createPageDataWithRoute($path);

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['path' => '/new']);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo($path, '/new');
        self::assertRouteNamed($path, '/' . str_repeat('a', 252) . '-1');
    }

    public function test_a_redirect_name_shortened_to_fit_the_name_column_is_still_unique(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $path = '/' . str_repeat('a', 254);
        $site = self::createPageDataWithRoute($path);
        self::createRouteWithPage('/' . str_repeat('a', 252) . '-1');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['route']), ['path' => '/new']);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo($path, '/new');
        self::assertRouteNamed($path, '/' . str_repeat('a', 252) . '-2');
    }

    public function test_get_a_routes_children_terminates_when_an_unrouted_child_page_loops_back_to_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRoutedPageAndUnroutedPageWhichAreEachOthersParent('/cycle');

        self::requestJsonLd($client, 'GET', self::iriOf($route) . '/children');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('children', 0);
    }

    public function test_patch_with_cascade_child_paths_terminates_when_an_unrouted_child_page_loops_back_to_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRoutedPageAndUnroutedPageWhichAreEachOthersParent('/cycle');

        self::requestJsonLd($client, 'PATCH', self::iriOf($route), ['path' => '/new-cycle', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/cycle', '/new-cycle');
    }

    public function test_patch_with_cascade_child_paths_to_a_path_under_its_own_path_terminates_when_an_unrouted_child_page_loops_back_to_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRoutedPageAndUnroutedPageWhichAreEachOthersParent('/cycle');

        self::requestJsonLd($client, 'PATCH', self::iriOf($route), ['path' => '/cycle/inner', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/cycle/inner');
        self::assertRouteRedirectsTo('/cycle', '/cycle/inner');
    }

    public function test_anonymous_users_cannot_access_route_children(): void
    {
        $client = static::createClient();
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentRoute']) . '/children');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_i_generate_a_route_for_a_path_that_already_exists_and_the_new_route_is_generated_with_a_postfix(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute('/programme');
        self::setPageDataTitle($site['pageData'], 'Programme');

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('route.schema.json');
        self::assertJsonNodeSame('path', '/programme-1');
        self::assertJsonNodeSame('name', 'programme-1');
    }

    public function test_i_can_get_all_the_redirects_for_a_route_in_a_single_request(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/redirect1', '/final-page');

        self::requestJsonLd($client, 'GET', self::iriOf($site['final']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('@id');
        self::assertJsonNodeSame('path', '/final-page');
        self::assertJsonNodeExists('redirectedFrom[0]');
        self::assertJsonNodeExists('redirectedFrom[0].redirectedFrom');
        self::assertJsonNodeSame('redirectedFrom[0].redirectedFrom[0].path', '/redirect1');
    }

    public function test_if_i_delete_a_route_aware_resource_the_associated_routes_should_also_be_deleted(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createPageDataWithRoute('/my-route');
        $pageDataIri = self::iriOf($site['pageData']);
        $routeIri = self::iriOf($site['route']);

        self::requestJsonLd($client, 'DELETE', $pageDataIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($pageDataIri);
        self::assertResourceNotExists($routeIri);
    }

    public function test_i_can_delete_a_draft_page_data_that_has_no_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataWithRoute(null);
        $pageDataIri = self::iriOf($site['pageData']);

        self::requestJsonLd($client, 'DELETE', $pageDataIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($pageDataIri);
    }

    public function test_a_resource_with_a_relation_to_a_route_should_return_the_path_instead_of_the_iri(): void
    {
        $client = static::createClient();
        $site = self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', self::iriOf($site['pageData']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('route', '/_/routes//my-route');
    }

    public function test_the_manifest_includes_component_iris_resolved_from_page_data_property_positions(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContainsIriMatching(0, '/\/component\/dummy_components\/[a-z0-9\-]+/');
    }

    public function test_the_manifest_includes_component_iris_from_allowed_components_restricted_page_data_property_positions_for_anonymous_users(): void
    {
        $client = static::createClient();
        self::createPageDataPropertyPositionWithAnAllowedComponentTypeInARestrictedGroup('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContainsIriMatching(0, '/\/component\/dummy_components\/[a-z0-9\-]+/');
    }

    public function test_the_manifest_for_a_nested_page_data_route_includes_parent_resource_iris_grouped_by_depth(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//conference/programme');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 2);
        self::assertCount(3, self::manifestIrisAtDepth(0));
        self::assertCount(3, self::manifestIrisAtDepth(1));
        self::assertManifestDepthContains(0, '/_/routes//conference');
        self::assertManifestDepthContains(1, '/_/routes//conference/programme');
    }

    public function test_the_manifest_for_a_nested_page_route_includes_parent_resource_iris_grouped_by_depth(): void
    {
        $client = static::createClient();
        self::createPageNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//conference/programme');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 2);
        self::assertCount(2, self::manifestIrisAtDepth(0));
        self::assertCount(2, self::manifestIrisAtDepth(1));
        self::assertManifestDepthContains(0, '/_/routes//conference');
        self::assertManifestDepthContains(1, '/_/routes//conference/programme');
    }

    public function test_i_can_get_a_manifest_of_all_unauthenticated_resources_that_should_be_loaded_for_a_route(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 1);
        self::assertSame('/_/routes//my-route', self::manifestRootIriAtDepth(0));
        self::assertManifestDepthContainsIriMatching(0, '/\/page_data\/page_data_with_components\/[a-z0-9\-]+/');
        self::assertManifestDepthContainsIriMatching(0, '/\/_\/pages\/[a-z0-9\-]+/');
        self::assertEveryManifestIriResolves();
    }

    public function test_the_manifest_of_a_redirecting_route_lists_only_iris_never_the_redirect_path(): void
    {
        $client = static::createClient();
        self::createRouteWithPageDataAndRedirect('/old', '/new');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//old');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContainsNoIriMatching(0, '/^\/new$/');
        self::assertEveryManifestIriResolves();
    }

    public function test_i_can_get_a_manifest_of_all_authenticated_resources_that_should_be_loaded_for_a_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 1);
        self::assertSame('/_/routes//my-route', self::manifestRootIriAtDepth(0));
        self::assertManifestDepthContainsIriMatching(0, '/\/page_data\/page_data_with_components\/[a-z0-9\-]+/');
        self::assertManifestDepthContainsIriMatching(0, '/\/_\/pages\/[a-z0-9\-]+/');
    }

    public function test_a_draft_component_resolved_via_page_data_property_is_excluded_from_the_anonymous_manifest(): void
    {
        $client = static::createClient();
        self::createPageDataWithADraftComponentInAPageDataPropertyPosition('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContainsNoIriMatching(0, '/\/component\/dummy_publishable_components\/[a-z0-9\-]+/');
    }

    public function test_a_draft_component_resolved_via_page_data_property_is_included_in_the_admin_manifest(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createPageDataWithADraftComponentInAPageDataPropertyPosition('/my-route');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContainsIriMatching(0, '/\/component\/dummy_publishable_components\/[a-z0-9\-]+/');
    }

    public function test_when_i_create_a_redirect_route_the_cache_should_be_cleared_for_the_route_being_redirected_to(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $route = self::createRouteWithPage('/my-route');

        self::requestJsonLd($client, 'POST', '/_/routes', '{"name": "/redirect", "path": "/redirect", "redirect": "/_/routes//my-route"}');

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged(self::iriOf($route));
    }

    public function test_when_i_delete_a_route_that_redirects_the_destination_route_should_be_cleared_from_the_cache(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/my-route', '/contact-new');
        $iris = array_map(self::iriOf(...), [$site['route'], $site['middle'], $site['final']]);

        self::requestJsonLd($client, 'DELETE', $iris[0]);

        self::assertResponseStatusCodeSame(204);
        foreach ($iris as $iri) {
            self::assertCacheTagPurged($iri);
        }
    }
}
