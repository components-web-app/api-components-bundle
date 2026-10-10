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

use Liip\ImagineBundle\Imagine\Cache\Resolver\WebPathResolver;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ImagineCompilerPass;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ImagineCompilerPassTest extends TestCase
{
    public function test_the_cache_manager_is_the_bundles(): void
    {
        $container = $this->createContainer();

        (new ImagineCompilerPass())->process($container);

        self::assertSame(CacheManager::class, $container->getDefinition('liip_imagine.cache.manager')->getClass());
    }

    public function test_a_flysystem_cache_resolver_is_given_the_filter_configuration(): void
    {
        $container = $this->createContainer();
        $container->setParameter('app.resolver_class', FlysystemCacheResolver::class);
        $container->setDefinition('app.web_path', (new Definition(WebPathResolver::class))->addTag('liip_imagine.cache.resolver', ['resolver' => 'web_path']));
        $container->setDefinition('app.kept', (new Definition(FlysystemCacheResolver::class))->setArgument('$filterConfiguration', null)->addTag('liip_imagine.cache.resolver', ['resolver' => 'kept']));
        $container->setDefinition('app.resolver', (new Definition(FlysystemCacheResolver::class))->addTag('liip_imagine.cache.resolver', ['resolver' => 'a']));
        $container->setDefinition('app.resolver_by_parameter', (new Definition('%app.resolver_class%'))->addTag('liip_imagine.cache.resolver', ['resolver' => 'b']));
        $container->setDefinition(FlysystemCacheResolver::class, (new Definition())->addTag('liip_imagine.cache.resolver', ['resolver' => 'c']));
        $container->setDefinition('app.resolver_with_positional_arguments', (new Definition(FlysystemCacheResolver::class, [1, 2, 3, 4]))->addTag('liip_imagine.cache.resolver', ['resolver' => 'd']));

        (new ImagineCompilerPass())->process($container);

        foreach (['app.resolver', 'app.resolver_by_parameter', FlysystemCacheResolver::class, 'app.resolver_with_positional_arguments'] as $id) {
            self::assertEquals(new Reference('liip_imagine.filter.configuration'), $container->getDefinition($id)->getArgument('$filterConfiguration'), $id);
        }
    }

    public function test_a_filter_configuration_the_application_set_is_kept(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.named', (new Definition(FlysystemCacheResolver::class))->setArgument('$filterConfiguration', null)->addTag('liip_imagine.cache.resolver', ['resolver' => 'a']));
        $container->setDefinition('app.positional', (new Definition(FlysystemCacheResolver::class, [1, 2, 3, 4, null]))->addTag('liip_imagine.cache.resolver', ['resolver' => 'b']));

        (new ImagineCompilerPass())->process($container);

        self::assertSame(['$filterConfiguration' => null], $container->getDefinition('app.named')->getArguments());
        self::assertSame([1, 2, 3, 4, null], $container->getDefinition('app.positional')->getArguments());
    }

    public function test_other_resolvers_are_left_alone(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.web_path', (new Definition(WebPathResolver::class))->addTag('liip_imagine.cache.resolver', ['resolver' => 'a']));
        $container->setDefinition('app.untagged', new Definition(FlysystemCacheResolver::class));

        (new ImagineCompilerPass())->process($container);

        self::assertSame([], $container->getDefinition('app.web_path')->getArguments());
        self::assertSame([], $container->getDefinition('app.untagged')->getArguments());
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('liip_imagine.cache.manager', new Definition());

        return $container;
    }
}
