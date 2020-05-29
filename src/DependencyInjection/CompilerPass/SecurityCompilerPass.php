<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class SecurityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // TODO `security.logout_listener.main` should be dynamic
        $container
            ->getDefinition('security.logout_listener.main')
            ->addMethodCall('addHandler', [new Reference('security.logout.handler.session')]);
    }
}
