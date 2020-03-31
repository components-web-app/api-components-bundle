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
                ->arrayNode('enabled_components')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('form')->defaultValue(true)->end()
                        ->booleanNode('collection')->defaultValue(true)->end()
                    ->end()
                ->end()
                ->arrayNode('user')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class_name')->isRequired()->end()
                        ->scalarNode('change_username_path')->isRequired()->end()
                        ->arrayNode('password_reset')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('repeat_ttl_seconds')->defaultValue(8600)->end()
                                ->integerNode('request_timeout_seconds')->defaultValue(3600)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mailer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('website_email_address')->defaultValue('%env(WEBSITE_EMAIL_ADDRESS)%')->end()
                        ->scalarNode('website_name')->defaultValue('%env(WEBSITE_NAME)%')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
