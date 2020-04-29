<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(FilesystemProvider::class);
        $taggedServices = $container->findTaggedServiceIds(FilesystemProvider::FILESYSTEM_ADAPTER_TAG);
        foreach ($taggedServices as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall(
                    'addAdapter',
                    [
                        $attributes['alias'],
                        new Reference($serviceId),
                    ]
                );
            }
        }
    }
}
