<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\RealtimeHubCompilerPass;
use Silverback\ApiComponentsBundle\SilverbackApiComponentsBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SilverbackApiComponentsBundleTest extends TestCase
{
    public function test_the_realtime_hub_is_chosen_before_the_container_is_optimised_so_the_hub_is_not_yet_decorated(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);

        (new SilverbackApiComponentsBundle())->build($container);

        $passes = array_map(static fn (CompilerPassInterface $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(RealtimeHubCompilerPass::class, $passes);
    }
}
