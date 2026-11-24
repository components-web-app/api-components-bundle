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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyHtmlContent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class AdminRolesTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use SiteTrait;

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function reachableRoles(): iterable
    {
        yield 'an admin, with the application\'s own ROLE_ADMIN: ROLE_USER kept' => ['ROLE_ADMIN', ['ROLE_ADMIN', 'ROLE_CMS_ADMIN', 'ROLE_USER']];
        yield 'a super admin' => ['ROLE_SUPER_ADMIN', ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH', 'ROLE_CMS_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER', 'ROLE_USER_ADMIN']];
        yield 'a cms admin' => ['ROLE_CMS_ADMIN', ['ROLE_CMS_ADMIN', 'ROLE_USER']];
        yield 'a user admin' => ['ROLE_USER_ADMIN', ['ROLE_USER', 'ROLE_USER_ADMIN']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('reachableRoles')]
    public function test_the_test_apps_role_hierarchy_is_the_applications_merged_with_the_admin_roles(string $role, array $expected): void
    {
        static::bootKernel();
        $reachable = static::getContainer()->get(RoleHierarchyInterface::class)->getReachableRoleNames([$role]);
        sort($reachable);

        self::assertSame($expected, $reachable);
    }

    /**
     * @return iterable<string, array{string, string, string|null, string, int}>
     */
    public static function cmsAdminRequests(): iterable
    {
        yield 'a component create' => ['POST', '/component/dummy_html_contents', '{"html": "<p>Hello</p>"}', 'application/ld+json', 201];
        yield 'the orphaned resource scan' => ['POST', '/_/orphaned_resources/scan', null, 'application/ld+json', 202];
        yield 'the orphaned resource report' => ['GET', '/_/orphaned_resources', null, 'application/ld+json', 404];
        yield 'the orphaned file scan' => ['POST', '/_/orphaned_files/scan', null, 'application/ld+json', 202];
        yield 'the orphaned file report' => ['GET', '/_/orphaned_files', null, 'application/ld+json', 404];
        yield 'the rendered HTML purge' => ['POST', '/_/rendered_html/purge', null, 'application/ld+json', 204];
        yield 'the collaboration states' => ['GET', '/_/collaboration_states', null, 'application/ld+json', 200];
        yield 'a realtime event' => ['POST', '/_/realtime', '{"clientId": "0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b", "event": "presence", "data": {}}', 'application/json', 202];
    }

    #[DataProvider('cmsAdminRequests')]
    public function test_a_user_with_only_the_cms_admin_role_can_use_the_content_endpoints(string $method, string $url, ?string $body, string $contentType, int $status): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_CMS_ADMIN']);

        self::requestJsonLd($client, $method, $url, $body, ['content-type' => $contentType]);

        self::assertResponseStatusCodeSame($status);
    }

    #[DataProvider('cmsAdminRequests')]
    public function test_a_user_with_only_the_user_admin_role_cannot_use_the_content_endpoints(string $method, string $url, ?string $body, string $contentType, int $cmsAdminStatus): void
    {
        self::assertNotSame(403, $cmsAdminStatus);
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, $method, $url, $body, ['content-type' => $contentType]);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_cms_admin_reads_a_components_usage_and_a_user_admin_cannot(): void
    {
        $client = static::createClient();
        $component = new DummyHtmlContent();
        self::persistAndFlush($component);
        $usage = self::iriOf($component) . '/usage';

        self::loginWithRoles($client, ['ROLE_CMS_ADMIN']);
        self::requestJsonLd($client, 'GET', $usage);
        self::assertResponseStatusCodeSame(200);

        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN'], 'user_admin', 'user_admin@example.com');
        self::requestJsonLd($client, 'GET', $usage);
        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_cms_admin_reads_a_routes_go_live_date_and_a_user_admin_does_not(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');

        self::loginWithRoles($client, ['ROLE_CMS_ADMIN']);
        self::requestJsonLd($client, 'GET', '/_/routes//launch');
        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('liveAt');

        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN'], 'user_admin', 'user_admin@example.com');
        self::requestJsonLd($client, 'GET', '/_/routes//launch');
        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('liveAt');
    }

    public function test_a_cms_admin_marks_a_page_reachable_without_a_route(): void
    {
        $client = static::createClient();
        $page = self::newPage('routeless');
        $page->uiComponent = 'TestComponent';
        $page->layout = self::newLayout();
        self::persistAndFlush($page->layout, $page);
        self::loginWithRoles($client, ['ROLE_CMS_ADMIN']);

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), '{"isReachableWithoutRoute": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('isReachableWithoutRoute', true);
    }

    public function test_a_user_admin_cannot_read_a_page_no_route_reaches(): void
    {
        $client = static::createClient();
        $page = self::createPage('routeless');
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(403);
    }
}
