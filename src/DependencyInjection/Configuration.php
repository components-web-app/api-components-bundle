<?php
/**
 * Created by PhpStorm.
 * User: silverbackis
 * Date: 28/02/2019
 * Time: 12:52
 */

namespace Silverback\ApiComponentBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('silverback_api_component');

        $rootNode
            ->children()
                ->arrayNode('mailer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('logo_src')->defaultNull()->end()
                        ->scalarNode('website_name')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('password_reset')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('repeat_ttl_seconds')->defaultValue(8600)->end()
                        ->integerNode('request_timeout_seconds')->defaultValue(3600)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
