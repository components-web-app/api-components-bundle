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
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class ReachableWithoutRouteTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheControlTrait;
    use JsonTrait;
    use ManifestTrait;
    use RouteSiteTrait;

    private const string FUTURE = '2999-01-01T00:00:00+00:00';

    public function test_a_routeless_page_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_routeless_page_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_manifest_of_a_routeless_page_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($site['page']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_page_marked_reachable_without_a_route_is_capped_at_the_next_go_live_moment_like_a_routed_page(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', (new \DateTimeImmutable())->modify('+60 seconds'));

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_marking_a_page_with_a_draft_route_reachable_without_a_route_does_not_make_it_readable_anonymously(): void
    {
        $client = static::createClient();
        $page = self::createRouteWithPage('/launch')->getPage();
        self::setRouteLiveAt('/launch', null);
        self::markReachableWithoutRoute($page);

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_marking_a_page_with_a_route_behind_route_security_reachable_without_a_route_does_not_make_it_readable_anonymously(): void
    {
        $client = static::createClient();
        $page = self::createRouteWithPage('/user-area/my-page')->getPage();
        self::markReachableWithoutRoute($page);

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_marking_a_page_with_a_scheduled_route_reachable_without_a_route_does_not_make_it_readable_anonymously(): void
    {
        $client = static::createClient();
        $page = self::createRouteWithPage('/launch')->getPage();
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));
        self::markReachableWithoutRoute($page);

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routed_page_under_a_draft_parent_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createPageNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', null);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routeless_page_under_a_live_parent_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/conference');

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_routeless_page_marked_reachable_without_a_route_under_a_live_parent_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/conference');
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_page_marked_reachable_without_a_route_under_a_draft_parent_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/conference');
        self::setRouteLiveAt('/conference', null);
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_component_in_a_page_marked_reachable_without_a_route_under_a_draft_parent_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/conference');
        self::setRouteLiveAt('/conference', null);
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['component']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_page_marked_reachable_without_a_route_under_a_scheduled_parent_route_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_page_marked_reachable_without_a_route_under_a_parent_route_behind_route_security_is_not_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/user-area');
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_page_marked_reachable_without_a_route_under_a_parent_route_behind_route_security_is_readable_by_a_permitted_user(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $site = self::createRoutelessPageWithAComponentNestedWithinRoute('/user-area');
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_routeless_parent_of_a_page_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['childPage']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentPageData']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_the_routeless_parent_of_a_page_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['childPage']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentComponent']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_page_data_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['parentPageData']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentPageData']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_template_of_page_data_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['parentPageData']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentTemplate']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_the_template_of_page_data_marked_reachable_without_a_route_is_readable_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['parentPageData']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentComponent']));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_marking_a_template_reachable_without_a_route_does_not_make_its_page_data_readable(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();
        self::markReachableWithoutRoute($site['parentTemplate']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['parentPageData']));

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_page_marked_reachable_without_a_route_is_not_listed_in_the_anonymous_page_collection(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_whether_a_page_is_reachable_without_a_route_is_not_exposed_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();
        self::markReachableWithoutRoute($site['page']);

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('isReachableWithoutRoute');
    }

    public function test_an_admin_can_see_that_a_page_is_not_reachable_without_a_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createRoutelessPageWithAComponentAndNoRoutedDescendant();

        self::requestJsonLd($client, 'GET', self::iriOf($site['page']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('isReachableWithoutRoute', false);
    }

    public function test_an_admin_can_mark_a_page_reachable_without_a_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::createValidPageWithAComponentGroup();

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), '{"isReachableWithoutRoute": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('isReachableWithoutRoute', true);
    }

    public function test_an_admin_can_mark_page_data_reachable_without_a_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createRoutelessParentPageDataWithAnUnroutedChildPage();

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['parentPageData']), '{"isReachableWithoutRoute": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('isReachableWithoutRoute', true);
    }
}
