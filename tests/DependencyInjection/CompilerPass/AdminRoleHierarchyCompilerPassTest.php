<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\AdminRoleHierarchyCompilerPass;
use Silverback\ApiComponentsBundle\DependencyInjection\SilverbackApiComponentsExtension;
use Silverback\ApiComponentsBundle\Security\AdminRoles;
use Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class AdminRoleHierarchyCompilerPassTest extends TestCase
{
    public function test_the_admin_hierarchy_is_the_cross_repository_contract(): void
    {
        self::assertSame([
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_USER_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            'ROLE_ADMIN' => ['ROLE_CMS_ADMIN'],
            'ROLE_CMS_ADMIN' => ['ROLE_USER'],
            'ROLE_USER_ADMIN' => ['ROLE_USER'],
        ], AdminRoles::HIERARCHY);
        self::assertSame(['ROLE_CMS_ADMIN', 'ROLE_USER_ADMIN'], [AdminRoles::CMS_ADMIN, AdminRoles::USER_ADMIN]);
    }

    public function test_security_bundle_replaces_a_prepended_role_list_with_the_applications_own_list_for_the_same_role(): void
    {
        self::assertSame(['ROLE_SHOP_ACCOUNTS'], self::processedRoleHierarchy()['ROLE_ADMIN']);
    }

    public function test_the_admin_roles_are_merged_into_the_applications_own_lists_for_the_same_role(): void
    {
        $roles = self::passed(['ROLE_ADMIN' => ['ROLE_SHOP_ACCOUNTS'], 'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'], 'ROLE_EDITOR' => ['ROLE_USER']]);

        self::assertSame([
            'ROLE_ADMIN' => ['ROLE_SHOP_ACCOUNTS', 'ROLE_CMS_ADMIN'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH', 'ROLE_USER_ADMIN'],
            'ROLE_EDITOR' => ['ROLE_USER'],
            'ROLE_CMS_ADMIN' => ['ROLE_USER'],
            'ROLE_USER_ADMIN' => ['ROLE_USER'],
        ], $roles);
    }

    public function test_a_site_role_under_role_admin_is_reachable_alongside_the_cms_admin_role(): void
    {
        $hierarchy = new RoleHierarchy(self::passed(self::processedRoleHierarchy()));

        $reachable = $hierarchy->getReachableRoleNames(['ROLE_ADMIN']);
        sort($reachable);

        self::assertSame(['ROLE_ADMIN', 'ROLE_CMS_ADMIN', 'ROLE_SHOP_ACCOUNTS', 'ROLE_USER'], $reachable);
    }

    public function test_a_site_role_admin_given_as_a_scalar_keeps_its_role_and_gains_the_cms_admin_role(): void
    {
        $processed = (new Processor())->processConfiguration(new MainConfiguration([], []), [['role_hierarchy' => AdminRoles::HIERARCHY], ['role_hierarchy' => ['ROLE_ADMIN' => 'ROLE_USER', 'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH']], 'firewalls' => ['main' => []]]])['role_hierarchy'];
        self::assertSame(['ROLE_USER'], $processed['ROLE_ADMIN']);

        $hierarchy = new RoleHierarchy(self::passed($processed));
        $admin = $hierarchy->getReachableRoleNames(['ROLE_ADMIN']);
        $superAdmin = $hierarchy->getReachableRoleNames(['ROLE_SUPER_ADMIN']);
        sort($admin);
        sort($superAdmin);

        self::assertSame(['ROLE_ADMIN', 'ROLE_CMS_ADMIN', 'ROLE_USER'], $admin);
        self::assertSame(['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH', 'ROLE_CMS_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_USER', 'ROLE_USER_ADMIN'], $superAdmin);
    }

    public function test_the_admin_hierarchy_alone_is_set_when_the_application_declares_none(): void
    {
        self::assertSame(AdminRoles::HIERARCHY, self::passed([]));
    }

    public function test_a_container_without_security_bundle_is_left_alone(): void
    {
        $container = new ContainerBuilder();

        (new AdminRoleHierarchyCompilerPass())->process($container);

        self::assertFalse($container->hasParameter(AdminRoleHierarchyCompilerPass::PARAMETER));
    }

    public function test_the_extension_prepends_the_admin_hierarchy_to_security_bundle(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });
        $container->prependExtensionConfig('security', ['role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_SHOP_ACCOUNTS']]]);
        $container->prependExtensionConfig('silverback_api_components', self::minimalConfig());

        (new SilverbackApiComponentsExtension())->prepend($container);

        self::assertSame([['role_hierarchy' => AdminRoles::HIERARCHY], ['role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_SHOP_ACCOUNTS']]]], $container->getExtensionConfig('security'));
    }

    public function test_the_extension_prepends_nothing_for_security_bundle_when_it_is_not_registered(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('silverback_api_components', self::minimalConfig());

        (new SilverbackApiComponentsExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('security'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function minimalConfig(): array
    {
        return [
            'refresh_token' => [
                'handler_id' => 'app.refresh_token.storage',
                'cookie_name' => 'api_components',
                'ttl' => 604800,
                'database_user_provider' => 'database',
            ],
            'user' => ['class_name' => 'App\Entity\User'],
            'website_name' => 'Test Website',
            'publishable' => ['permission' => "is_granted('ROLE_CMS_ADMIN')"],
        ];
    }

    /**
     * @param array<string, list<string>> $roles
     *
     * @return array<string, list<string>>
     */
    private static function passed(array $roles): array
    {
        $container = new ContainerBuilder();
        $container->setParameter(AdminRoleHierarchyCompilerPass::PARAMETER, $roles);

        (new AdminRoleHierarchyCompilerPass())->process($container);

        $passed = $container->getParameter(AdminRoleHierarchyCompilerPass::PARAMETER);
        self::assertIsArray($passed);

        return $passed;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function processedRoleHierarchy(): array
    {
        return (new Processor())->processConfiguration(new MainConfiguration([], []), [['role_hierarchy' => AdminRoles::HIERARCHY], ['role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_SHOP_ACCOUNTS']], 'firewalls' => ['main' => []]]])['role_hierarchy'];
    }
}
