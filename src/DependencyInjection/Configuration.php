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
                ->scalarNode('website_name')->isRequired()->end()
                ->scalarNode('table_prefix')->defaultValue('_acb_')->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('tokens')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
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
                        ->arrayNode('email_verification')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('default')->isRequired()->end()
                                ->booleanNode('verify_on_register')->isRequired()->end()
                                ->booleanNode('deny_unverified_login')->isRequired()->end()
                            ->end()
                        ->end()
                        ->arrayNode('change_email_address')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('default_verify_path')->isRequired()->end()
                            ->end()
                        ->end()
                        ->arrayNode('password_reset')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('default_reset_path')->isRequired()->end()
                                ->integerNode('repeat_ttl_seconds')->defaultValue(8600)->end()
                                ->integerNode('request_timeout_seconds')->defaultValue(3600)->end()
                            ->end()
                        ->end()
                        ->arrayNode('emails')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('user_welcome')->defaultValue(true)->end()
                                ->booleanNode('user_enabled')->defaultValue(true)->end()
                                ->booleanNode('user_username_changed')->defaultValue(true)->end()
                                ->booleanNode('user_password_changed')->defaultValue(true)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
