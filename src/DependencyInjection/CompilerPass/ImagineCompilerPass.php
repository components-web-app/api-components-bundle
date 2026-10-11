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

use Liip\ImagineBundle\Imagine\Filter\FilterManager as LiipFilterManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FilterManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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

        $filterManager = $container->getDefinition('liip_imagine.filter.manager');
        if (LiipFilterManager::class === $container->getParameterBag()->resolveValue($filterManager->getClass())) {
            $filterManager->setClass(FilterManager::class);
        }

        foreach (array_keys($container->findTaggedServiceIds('liip_imagine.cache.resolver')) as $id) {
            $resolver = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($resolver->getClass() ?? $id);
            if (!\is_string($class) || !is_a($class, FlysystemCacheResolver::class, true)) {
                continue;
            }
            self::setUnlessSet($resolver, '$filterConfiguration', 4, new Reference('liip_imagine.filter.configuration'));
            self::setUnlessSet($resolver, '$cacheControl', 5, new Reference(UploadableCacheControl::class));
        }
    }

    private static function setUnlessSet(Definition $resolver, string $name, int $position, Reference $value): void
    {
        $arguments = $resolver->getArguments();
        if (\array_key_exists($name, $arguments) || \array_key_exists($position, $arguments)) {
            return;
        }
        $resolver->setArgument($name, $value);
    }
}
