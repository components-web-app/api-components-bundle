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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class RouteScheduleTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheControlTrait;
    use JsonTrait;
    use ManifestTrait;
    use RouteSiteTrait;

    private const string FUTURE = '2999-01-01T00:00:00+00:00';

    private const string PAST = '2000-01-01T00:00:00+00:00';

    public function test_a_route_created_without_an_explicit_go_live_date_is_live_immediately(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_anonymous_user_cannot_resolve_a_route_before_its_go_live_date(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_an_anonymous_user_cannot_resolve_a_route_which_has_no_go_live_date(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_an_anonymous_user_can_resolve_a_route_whose_go_live_date_has_passed(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_authenticated_user_without_the_publishable_permission_cannot_resolve_a_scheduled_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_route_restricted_by_route_security_still_reports_that_authentication_is_required(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', '/_/routes//user-area/my-page');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routed_child_under_an_unrouted_parent_page_is_live(): void
    {
        $client = static::createClient();
        self::createPageDataWhoseParentHasNoRoute('/child-path');

        self::requestJsonLd($client, 'GET', '/_/routes//child-path');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routed_child_under_a_draft_parent_route_is_not_live(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', null);

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_live_child_under_a_scheduled_parent_route_inherits_the_parent_go_live_date(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_child_with_an_earlier_go_live_date_than_its_parent_is_gated_by_the_parent(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));
        self::setRouteLiveAt('/conference/programme', new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(404);
        self::assertRouteNotLive('/conference/programme');
    }

    public function test_a_child_with_a_later_go_live_date_than_its_live_parent_is_gated_by_its_own_date(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference/programme', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//conference');

        self::assertResponseStatusCodeSame(200);
        self::assertRouteNotLive('/conference/programme');
    }

    public function test_a_nested_static_page_under_a_scheduled_page_data_parent_inherits_the_parent_go_live_date(): void
    {
        $client = static::createClient();
        self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_child_becomes_live_once_its_parent_go_live_date_is_moved_into_the_past(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(200);
        self::assertRouteLive('/conference/programme');
    }

    public function test_a_route_generated_under_a_scheduled_parent_inherits_the_parent_go_live_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setPageDataTitle($site['pageData'], 'Schedule');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'POST', '/_/routes/generate', ['pageData' => self::iriOf($site['pageData'])]);

        self::assertResponseStatusCodeSame(201);
        self::assertRouteNotLive('/conference/schedule');
    }

    public function test_an_admin_can_resolve_a_scheduled_route_and_see_its_go_live_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('liveAt');
    }

    public function test_an_admin_can_resolve_a_draft_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_admin_can_schedule_a_route_by_patching_its_go_live_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'PATCH', self::iriOf($route), ['liveAt' => self::FUTURE]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('liveAt');
        self::assertRouteNotLive('/launch');
    }

    public function test_patching_a_parent_go_live_date_cascades_to_already_persisted_descendants(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['liveAt' => self::FUTURE]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteNotLive('/conference/programme');
    }

    public function test_the_go_live_date_is_not_exposed_to_anonymous_users(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', '/_/routes//launch');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('liveAt');
    }

    public function test_an_admin_loading_a_route_through_the_redirects_endpoint_can_see_its_go_live_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($route) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('liveAt');
    }

    public function test_an_admin_loading_a_route_through_the_redirects_endpoint_can_see_its_effective_go_live_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', self::iriOf($route) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('_metadata.effectiveLiveAt');
    }

    public function test_a_live_child_route_under_a_scheduled_parent_reports_the_parents_date_as_its_effective_date(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));
        self::setRouteLiveAt('/conference/programme', new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'GET', self::iriOf($site['route']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('liveAt', '/^2000-01-01/');
        self::assertJsonNodeMatches('_metadata.effectiveLiveAt', '/^2999-01-01/');
        self::assertRouteNotLive('/conference/programme');
    }

    public function test_neither_go_live_date_is_exposed_to_anonymous_users_through_the_redirects_endpoint(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', self::iriOf($route) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('liveAt');
        self::assertJsonNodeNotExists('_metadata.effectiveLiveAt');
    }

    public function test_an_anonymous_user_cannot_get_the_manifest_for_a_route_before_its_go_live_date(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_an_anonymous_user_cannot_get_the_manifest_for_a_scheduled_route_by_page_data_id(): void
    {
        $client = static::createClient();
        $site = self::createPageDataWithRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['pageData']));

        self::assertResponseStatusCodeSame(404);
    }

    public function test_an_admin_can_get_the_manifest_for_a_scheduled_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createPageDataWithRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//launch');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('/_/routes//launch', self::manifestRootIriAtDepth(0));
    }

    public function test_a_scheduled_route_does_not_appear_in_the_anonymous_route_collection(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_draft_route_does_not_appear_in_the_anonymous_route_collection(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_draft_route_appears_in_the_admin_route_collection_so_it_can_be_selected_before_launch(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_a_scheduled_route_appears_in_the_admin_route_collection(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_a_page_whose_only_route_is_scheduled_does_not_appear_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_page_whose_only_route_is_scheduled_cannot_be_loaded_anonymously(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/launch');
        $pageIri = self::iriOf($route->getPage());
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', $pageIri);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_reachable_only_via_a_scheduled_route_is_not_publicly_readable(): void
    {
        $client = static::createClient();
        $site = self::createComponentInRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_both_a_scheduled_and_a_live_route_is_publicly_readable(): void
    {
        $client = static::createClient();
        $site = self::createComponentInRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));
        self::placeComponentInARouteWithThePath($site['component'], '/any-page');

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_reachable_only_via_a_scheduled_route_is_readable_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createComponentInRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_scheduled_page_data_route_is_not_publicly_readable(): void
    {
        $client = static::createClient();
        $site = self::createComponentInAPageDataRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_route_redirecting_to_a_scheduled_route_still_reports_the_redirect_path(): void
    {
        $client = static::createClient();
        self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//old');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('redirectPath', '/launch');
    }

    public function test_a_route_redirecting_to_a_scheduled_route_does_not_expose_the_scheduled_page(): void
    {
        $client = static::createClient();
        self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//old');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('page');
    }

    public function test_a_scheduled_route_is_not_exposed_in_a_live_routes_redirect_tree(): void
    {
        $client = static::createClient();
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');
        self::setRouteLiveAt('/old', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['final']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('redirectedFrom[0].redirectedFrom[0]');
    }

    public function test_a_scheduled_route_is_exposed_in_a_live_routes_redirect_tree_for_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');
        self::setRouteLiveAt('/old', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['final']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('redirectedFrom[0].redirectedFrom[0].path', '/old');
    }

    public function test_an_admin_sees_a_scheduled_child_in_the_children_tree(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference/programme', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentRoute']) . '/children');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('children', 1);
    }

    public function test_patch_a_scheduled_parent_route_path_with_cascade_child_paths_still_cascades(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentRoute']), ['path' => '/new-conference', 'cascadeChildPaths' => true]);

        self::assertResponseStatusCodeSame(200);
        self::assertRouteRedirectsTo('/conference', '/new-conference');
        self::assertRouteRedirectsTo('/conference/programme', '/new-conference/programme');
    }

    public function test_a_live_route_under_a_scheduled_parent_is_not_listed_anonymously_and_cannot_be_resolved(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');

        self::requestJsonLd($client, 'GET', '/_/routes//conference/programme');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_live_route_under_a_draft_parent_is_not_listed_anonymously(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', null);

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_live_route_under_a_scheduled_grandparent_is_not_listed_anonymously(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinTwoRoutes('/conference/programme/day-one', '/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_routed_child_under_an_unrouted_ancestor_is_still_listed_anonymously(): void
    {
        $client = static::createClient();
        self::createPageDataWhoseParentHasNoRoute('/child-path');

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/child-path');
    }

    public function test_a_route_with_no_parent_is_still_listed_anonymously(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/launch');
    }

    public function test_the_anonymous_route_collection_count_excludes_a_route_gated_by_its_ancestor(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));
        self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', '/_/routes?itemsPerPage=10');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].path', '/launch');
    }

    public function test_an_admin_still_sees_a_route_gated_by_its_ancestor_in_the_route_collection(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes?itemsPerPage=10');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 2);
    }

    public function test_a_cycle_in_the_page_hierarchy_still_returns_the_anonymous_route_collection(): void
    {
        $client = static::createClient();
        self::createTwoPagesWhichAreEachOthersParent('/cycle-one', '/cycle-two');

        self::requestJsonLd($client, 'GET', '/_/routes?itemsPerPage=10');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 2);
    }

    public function test_a_cycle_in_the_page_hierarchy_does_not_hide_a_route_gated_within_it(): void
    {
        $client = static::createClient();
        self::createTwoPagesWhichAreEachOthersParent('/cycle-one', '/cycle-two');
        self::setRouteLiveAt('/cycle-two', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes?itemsPerPage=10');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 0);
    }

    public function test_a_page_whose_route_is_gated_by_a_scheduled_ancestor_does_not_appear_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_an_admin_still_sees_a_page_whose_route_is_gated_by_a_scheduled_ancestor(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_a_routed_page_under_an_unrouted_ancestor_still_appears_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        self::createPageWhoseParentHasNoRoute('/child-path');

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_page_data_whose_route_is_gated_by_a_scheduled_ancestor_does_not_appear_in_the_anonymous_page_data_collection(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/page_data/page_datas');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_page_data_under_an_unrouted_ancestor_still_appears_in_the_anonymous_page_data_collection(): void
    {
        $client = static::createClient();
        self::createPageDataWhoseParentHasNoRoute('/child-path');

        self::requestJsonLd($client, 'GET', '/page_data/page_datas');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_an_authenticated_user_without_the_publishable_permission_cannot_resolve_a_draft_route_behind_route_security_they_satisfy(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createRouteWithPage('/user-area/launch');
        self::setRouteLiveAt('/user-area/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes//user-area/launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_an_authenticated_user_without_the_publishable_permission_cannot_get_the_manifest_for_a_scheduled_route(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createPageDataWithRoute('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//launch');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_the_anonymous_route_collection_is_cached_until_the_next_go_live_moment(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable('+120 seconds'));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(120);
        self::assertSharedMaxAgeAtLeast(100);
    }

    public function test_the_cache_lifetime_is_capped_at_the_earliest_future_go_live_moment_ignoring_past_ones(): void
    {
        $client = static::createClient();
        self::persistAndFlush(
            self::newRoute('/launched')->setLiveAt(new \DateTimeImmutable('-1 day')),
            self::newRoute('/launch')->setLiveAt(new \DateTimeImmutable('+120 seconds')),
            self::newRoute('/later')->setLiveAt(new \DateTimeImmutable('+1200 seconds')),
        );

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(120);
        self::assertSharedMaxAgeAtLeast(100);
    }

    public function test_a_route_redirecting_to_live_page_data_carries_the_page_data(): void
    {
        $client = static::createClient();
        $pageData = self::createRouteRedirectingToPageData('/old', '/launch');

        self::requestJsonLd($client, 'GET', '/_/routes//old');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('pageData', $pageData);
    }

    public function test_a_route_redirecting_to_scheduled_page_data_does_not_expose_the_page_data(): void
    {
        $client = static::createClient();
        self::createRouteRedirectingToPageData('/old', '/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes//old');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('redirectPath', '/launch');
        self::assertJsonNodeNotExists('pageData');
    }

    public function test_a_scheduled_middle_route_and_everything_redirecting_through_it_are_pruned_from_the_redirect_tree(): void
    {
        $client = static::createClient();
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');
        self::setRouteLiveAt($site['middle']->getPath(), new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($site['final']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/launch');
        self::assertJsonNodeNotExists('redirectedFrom[0]');
    }

    public function test_a_live_middle_route_is_kept_in_the_redirect_tree(): void
    {
        $client = static::createClient();
        $site = self::createRouteRedirectingThroughAMiddleRouteTo('/old', '/launch');

        self::requestJsonLd($client, 'GET', self::iriOf($site['final']) . '/redirects');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('redirectedFrom[0].path', $site['middle']->getPath());
        self::assertJsonNodeSame('redirectedFrom[0].redirectedFrom[0].path', '/old');
    }

    private static function createRouteRedirectingToPageData(string $path, string $targetPath): string
    {
        $template = static::newPage('redirect-target-template', true);
        $pageData = new PageData();
        $pageData->page = $template;
        $target = static::newRoute($targetPath)->setPageData($pageData);
        $route = static::newRoute($path)->setRedirect($target);
        static::persistAndFlush($template, $pageData, $target, $route);

        return static::iriOf($pageData);
    }
}
