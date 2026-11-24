<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentsBundle\Security\AdminRoles;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AdminRoleHierarchyCompilerPass implements CompilerPassInterface
{
    public const string PARAMETER = 'security.role_hierarchy.roles';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::PARAMETER)) {
            return;
        }

        $roles = $container->getParameter(self::PARAMETER);
        if (!\is_array($roles)) {
            return;
        }

        foreach (AdminRoles::HIERARCHY as $role => $reachable) {
            $roles[$role] = array_values(array_unique([...($roles[$role] ?? []), ...$reachable]));
        }

        $container->setParameter(self::PARAMETER, $roles);
    }
}
