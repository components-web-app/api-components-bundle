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

use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition('liip_imagine.cache.manager');
        $definition->setClass(CacheManager::class);

        foreach (array_keys($container->findTaggedServiceIds('liip_imagine.cache.resolver')) as $id) {
            $resolver = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($resolver->getClass() ?? $id);
            if (!\is_string($class) || !is_a($class, FlysystemCacheResolver::class, true) || \array_key_exists('$filterConfiguration', $resolver->getArguments()) || \array_key_exists(4, $resolver->getArguments())) {
                continue;
            }
            $resolver->setArgument('$filterConfiguration', new Reference('liip_imagine.filter.configuration'));
        }
    }
}
