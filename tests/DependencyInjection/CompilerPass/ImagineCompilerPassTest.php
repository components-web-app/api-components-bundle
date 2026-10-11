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
use Liip\ImagineBundle\Imagine\Filter\FilterManager as LiipFilterManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ImagineCompilerPass;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FilterManager;
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

    public function test_liips_filter_manager_is_replaced_with_the_bundles(): void
    {
        $container = $this->createContainer();
        $container->setParameter('liip_imagine.filter.manager.class', LiipFilterManager::class);
        $container->getDefinition('liip_imagine.filter.manager')->setClass('%liip_imagine.filter.manager.class%');

        (new ImagineCompilerPass())->process($container);

        self::assertSame(FilterManager::class, $container->getDefinition('liip_imagine.filter.manager')->getClass());
    }

    public function test_a_filter_manager_class_the_application_set_is_kept(): void
    {
        $container = $this->createContainer();
        $container->getDefinition('liip_imagine.filter.manager')->setClass('App\\Imagine\\FilterManager');

        (new ImagineCompilerPass())->process($container);

        self::assertSame('App\\Imagine\\FilterManager', $container->getDefinition('liip_imagine.filter.manager')->getClass());
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

        self::assertNull($container->getDefinition('app.named')->getArgument('$filterConfiguration'));
        self::assertArrayNotHasKey('$filterConfiguration', $container->getDefinition('app.positional')->getArguments());
        self::assertNull($container->getDefinition('app.positional')->getArgument(4));
    }

    public function test_a_flysystem_cache_resolver_is_given_the_uploadable_cache_control(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.resolver', (new Definition(FlysystemCacheResolver::class))->addTag('liip_imagine.cache.resolver', ['resolver' => 'a']));
        $container->setDefinition('app.resolver_with_a_filter_configuration', (new Definition(FlysystemCacheResolver::class, [1, 2, 3, 4, null]))->addTag('liip_imagine.cache.resolver', ['resolver' => 'b']));

        (new ImagineCompilerPass())->process($container);

        foreach (['app.resolver', 'app.resolver_with_a_filter_configuration'] as $id) {
            self::assertEquals(new Reference(UploadableCacheControl::class), $container->getDefinition($id)->getArgument('$cacheControl'), $id);
        }
    }

    public function test_a_cache_control_the_application_set_is_kept(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.named', (new Definition(FlysystemCacheResolver::class))->setArgument('$cacheControl', null)->addTag('liip_imagine.cache.resolver', ['resolver' => 'a']));
        $container->setDefinition('app.positional', (new Definition(FlysystemCacheResolver::class, [1, 2, 3, 4, null, null]))->addTag('liip_imagine.cache.resolver', ['resolver' => 'b']));

        (new ImagineCompilerPass())->process($container);

        self::assertNull($container->getDefinition('app.named')->getArgument('$cacheControl'));
        self::assertEquals(new Reference('liip_imagine.filter.configuration'), $container->getDefinition('app.named')->getArgument('$filterConfiguration'));
        self::assertSame([1, 2, 3, 4, null, null], $container->getDefinition('app.positional')->getArguments());
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
        $container->setDefinition('liip_imagine.filter.manager', new Definition(LiipFilterManager::class));

        return $container;
    }
}
