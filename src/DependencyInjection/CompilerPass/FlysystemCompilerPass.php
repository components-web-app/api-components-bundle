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

use League\Flysystem\Filesystem;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $adapters = $container->findTaggedServiceIds(FilesystemProvider::FILESYSTEM_ADAPTER_TAG);
        foreach ($adapters as $adaperId => $tags) {
            foreach ($tags as $attributes) {
                $definition = new Definition();
                $definition
                    ->setClass(Filesystem::class)
                    ->setFactory([new Reference(FilesystemFactory::class), 'create'])
                    ->setArguments([
                        $attributes['alias'],
                        $attributes['config'] ?? [],
                    ]);
                $serviceName = sprintf('api_components.filesystem.%s', $attributes['alias']);
                $container
                    ->setDefinition($serviceName, $definition)
                    ->addTag(FilesystemProvider::FILESYSTEM_TAG, ['alias' => $attributes['alias']]);
            }
        }
    }
}
