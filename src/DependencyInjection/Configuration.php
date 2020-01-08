<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('silverback_api_component');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('table_prefix')->defaultValue('_acb_')->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
