<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Security;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CookieTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CookieTrait;
    use JsonTrait;
    use SecuritySiteTrait;

    public function test_a_route_retrieved_by_id_is_restricted_based_on_the_security_policy(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($route));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_route_retrieved_by_path_is_restricted_based_on_the_security_policy(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', '/_/routes//user-area/my-page');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_route_retrieved_by_path_is_allowed_if_not_in_config(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/my-page');

        self::requestJsonLd($client, 'GET', '/_/routes//my-page');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_collection_of_routes_will_not_include_pages_a_user_has_no_access_to(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_super_admin_can_get_a_collection_of_routes(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('member[0]');
    }

    public function test_an_anonymous_route_collection_lists_the_routes_outside_route_security_only(): void
    {
        $client = static::createClient();
        self::persistAndFlush(self::newRoute('/public'), self::newRoute('/user-area'), self::newRoute('/user-area/my-page'));

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/public');
    }

    public function test_a_user_satisfying_route_security_gets_the_routes_under_it_in_the_route_collection(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::persistAndFlush(self::newRoute('/public'), self::newRoute('/user-area'), self::newRoute('/user-area/my-page'));

        self::requestJsonLd($client, 'GET', '/_/routes?order[path]=asc');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 3);
        self::assertJsonNodeSame('member[0].path', '/public');
        self::assertJsonNodeSame('member[1].path', '/user-area');
        self::assertJsonNodeSame('member[2].path', '/user-area/my-page');
    }

    public function test_a_route_whose_path_only_resembles_a_route_security_pattern_is_public(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/user-are');

        self::requestJsonLd($client, 'GET', '/_/routes//user-are');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_route_security_pattern_without_a_leading_wildcard_does_not_restrict_a_path_holding_it_further_in(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/other/user-area/x');

        self::requestJsonLd($client, 'GET', '/_/routes');
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/other/user-area/x');

        self::requestJsonLd($client, 'GET', '/_/routes//other/user-area/x');
        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_route_security_pattern_with_a_leading_wildcard_restricts_a_path_holding_it_further_in(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/other/members-area/x');

        self::requestJsonLd($client, 'GET', '/_/routes');
        self::assertJsonNodeSame('totalItems', 0);

        self::requestJsonLd($client, 'GET', '/_/routes//other/members-area/x');
        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_restricted_route_cannot_be_loaded_by_an_anonymous_user(): void
    {
        $client = static::createClient();
        $created = self::createComponentInRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($created['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_restricted_route_can_be_loaded_by_an_authorised_user(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $created = self::createComponentInRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($created['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_within_a_restricted_route_and_a_public_route_can_be_loaded_by_an_anonymous_user(): void
    {
        $client = static::createClient();
        $componentIri = self::iriOf(self::createComponentInRoute('/user-area/my-page')['component']);
        self::placeComponentInRoute($componentIri, '/any-page');

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_page_data_resource_which_has_a_restricted_route_is_restricted_for_an_anonymous_user(): void
    {
        $client = static::createClient();
        $created = self::createComponentInPageDataRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_page_data_resource_which_has_a_restricted_route_can_be_loaded_by_an_authorised_user(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $created = self::createComponentInPageDataRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_page_data_resource_which_is_restricted_by_api_platform_security_metadata_is_also_restricted(): void
    {
        $client = static::createClient();
        $created = self::createComponentInRestrictedPageDataRoute(null);

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_page_data_resource_which_is_restricted_by_api_platform_security_metadata_is_allowed_for_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createComponentInRestrictedPageDataRoute(null);

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_restricted_in_page_data_but_allowed_in_a_static_page_route_is_allowed(): void
    {
        $client = static::createClient();
        $created = self::createComponentInRestrictedPageDataRoute(null);
        self::placeComponentInRoute($created['component'], '/any-page');

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_forbidden_in_a_static_route_but_allowed_within_page_data_is_allowed(): void
    {
        $client = static::createClient();
        $componentIri = self::iriOf(self::createComponentInRoute('/user-area/my-page')['component']);
        self::placeComponentInPageDataRoute($componentIri, '/any-path');

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_unrouted_template_of_a_page_data_resource_which_has_a_restricted_route_is_also_restricted(): void
    {
        $client = static::createClient();
        $created = self::createComponentInPageDataRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', $created['page']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_within_page_data_with_a_route_the_page_data_and_its_dynamic_page_are_all_accessible(): void
    {
        $client = static::createClient();
        $created = self::createComponentInPageDataRoute('/my-page');

        self::requestJsonLd($client, 'GET', $created['component']);
        self::assertResponseStatusCodeSame(200);

        self::requestJsonLd($client, 'GET', $created['pageData']);
        self::assertResponseStatusCodeSame(200);

        self::requestJsonLd($client, 'GET', $created['page']);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routable_resource_is_forbidden_to_be_loaded_without_a_route(): void
    {
        $client = static::createClient();
        $page = self::createPage();

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routable_resource_with_a_public_route_can_be_loaded(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($route->getPage()));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routable_resource_with_a_restricted_route_cannot_be_loaded_by_a_public_user(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/user-area/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($route->getPage()));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routable_resource_without_a_route_can_be_loaded_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::createPage();

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_site_settings_can_be_loaded_anonymously(): void
    {
        $client = static::createClient();
        self::createSiteConfigParameter();

        self::requestJsonLd($client, 'GET', '/_/site_config_parameters');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_site_settings_cannot_be_deleted_anonymously(): void
    {
        $client = static::createClient();
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'DELETE', self::iriOf($parameter));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_site_settings_can_be_deleted_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'DELETE', self::iriOf($parameter));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_site_settings_cannot_be_fetched_individually_anonymously(): void
    {
        $client = static::createClient();
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'GET', self::iriOf($parameter));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_site_settings_can_be_fetched_individually_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'GET', self::iriOf($parameter));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_site_settings_cannot_be_created_by_anonymous_users(): void
    {
        $client = static::createClient();
        self::createSiteConfigParameter();

        self::requestJsonLd($client, 'POST', '/_/site_config_parameters', ['key' => 'bob', 'value' => 'uncle']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_site_settings_can_be_created_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/_/site_config_parameters', ['key' => 'bob', 'value' => 'uncle']);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_site_settings_cannot_be_updated_by_anonymous_users(): void
    {
        $client = static::createClient();
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'PUT', self::iriOf($parameter), ['key' => 'new_key', 'value' => 'new_value']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_site_settings_can_be_updated_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameter = self::createSiteConfigParameter();

        self::requestJsonLd($client, 'PUT', self::iriOf($parameter), ['key' => 'new_key', 'value' => 'new_value']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_jwt_cookie_is_cleared_when_the_authenticated_user_is_deleted_from_the_database(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::deleteUser($user);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseCookieExpiresWithin('api_components', 2);
    }

    public function test_the_mercure_cookie_is_cleared_with_the_jwt_cookie_when_the_authenticated_user_is_deleted_from_the_database(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::deleteUser($user);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertTrue(self::responseCookie('api_components')->isCleared());
        self::assertMercureCookieCleared();
    }

    public function test_a_successful_request_to_me_keeps_the_jwt_cookie(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHasNoCookie('api_components');
        self::assertResponseHasNoCookie(self::mercureCookieName());
    }

    public function test_me_resolves_the_user_by_username_even_after_the_user_entity_is_recreated_with_a_new_database_id(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::deleteUser($user);
        self::recreateAdminWithUsername('new_user');

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('username', 'new_user');
    }

    public function test_a_routeless_parent_page_data_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_page_template_of_a_routeless_parent_page_data_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentTemplate']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_the_template_of_a_routeless_parent_page_data_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_manifest_of_a_routeless_parent_page_data_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentManifest']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routeless_parent_page_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentPage']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_routeless_parent_page_is_readable_anonymously_when_a_routed_child_page_declares_it_as_its_parent(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routeless_ancestor_two_levels_above_a_routed_page_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $created = self::createChainOfRoutelessPages(2, '/deep-child');

        self::requestJsonLd($client, 'GET', $created['chainRoot']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_routeless_ancestor_two_levels_above_a_routed_page_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $created = self::createChainOfRoutelessPages(2, '/deep-child');

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_on_a_routed_page_is_readable_anonymously_when_it_is_also_placed_on_a_routeless_page(): void
    {
        $client = static::createClient();
        $componentIri = self::createComponentOnRoutedPageAlsoOnRoutelessPage('/shared-component');

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_routeless_page_which_nothing_routes_to_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $componentIri = self::createRoutelessPageWithNoRoutedDescendant();

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_an_admin_can_read_a_component_in_a_routeless_page_which_nothing_routes_to(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::createRoutelessPageWithNoRoutedDescendant();

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_admin_can_read_a_component_behind_a_route_security_route_whose_role_they_do_not_hold(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createComponentInRoute('/user-area/my-page');

        self::requestJsonLd($client, 'GET', self::iriOf($created['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routeless_page_whose_only_child_page_has_no_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithUnroutedChild();

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_routeless_page_with_no_routed_descendant_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithUnroutedChild();

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_an_admin_can_read_a_component_in_a_routeless_page_with_no_routed_descendant(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createRoutelessParentPageDataWithUnroutedChild();

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routeless_parent_is_not_readable_anonymously_when_its_only_routed_descendant_has_no_go_live_date(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');
        self::setRouteLiveAt('/child-path', null);

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_of_a_routeless_parent_is_not_readable_anonymously_when_its_only_routed_descendant_has_no_go_live_date(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');
        self::setRouteLiveAt('/child-path', null);

        self::requestJsonLd($client, 'GET', $created['parentComponent']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routeless_parent_is_not_readable_anonymously_when_its_only_routed_descendant_is_scheduled_for_the_future(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');
        self::setRouteLiveAt('/child-path', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_an_admin_can_read_a_routeless_parent_whose_only_routed_descendant_is_scheduled_for_the_future(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createRoutelessParentPageDataWithRoutedChild('/child-path');
        self::setRouteLiveAt('/child-path', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_routeless_parent_whose_only_routed_descendant_is_behind_route_security_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageDataWithRoutedChild('/user-area/child-path');

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routeless_parent_whose_only_routed_descendant_is_behind_route_security_is_readable_by_a_permitted_user(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $created = self::createRoutelessParentPageDataWithRoutedChild('/user-area/child-path');

        self::requestJsonLd($client, 'GET', $created['parentPageData']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_parent_chain_which_loops_back_on_itself_does_not_prevent_a_response(): void
    {
        $client = static::createClient();
        $pageDataIri = self::createRoutelessPageDataCycle();

        self::requestJsonLd($client, 'GET', $pageDataIri);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routeless_page_reachable_from_a_routed_descendant_is_readable_but_is_not_listed_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        $created = self::createRoutelessParentPageWithRoutedChild('/child-path');

        self::requestJsonLd($client, 'GET', $created['parentPage']);
        self::assertResponseStatusCodeSame(200);

        self::requestJsonLd($client, 'GET', '/_/pages?order[reference]=asc');
        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'routed child page');
    }

    public function test_a_routeless_page_with_no_routed_descendant_does_not_appear_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        self::createRoutelessParentPageDataWithUnroutedChild();

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    private static function deleteUser(User $user): void
    {
        $manager = static::getEntityManager();
        $manager->remove($manager->find(User::class, $user->getId()));
        $manager->flush();
        static::rebootKernel();
    }

    private static function recreateAdminWithUsername(string $username): void
    {
        $user = new User();
        $user
            ->setRoles(['ROLE_ADMIN'])
            ->setUsername($username)
            ->setEmailAddress('recreated@example.com')
            ->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, bin2hex(random_bytes(8))))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        static::persistAndFlush($user);
    }
}
