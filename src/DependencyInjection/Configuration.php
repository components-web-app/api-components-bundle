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

namespace Silverback\ApiComponentsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('silverback_api_components');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
                ->scalarNode('website_name')->isRequired()->end()
                ->scalarNode('table_prefix')->defaultValue('_acb_')->end()
                ->scalarNode('metadata_key')->defaultValue('_metadata')->end()
            ->end();

        $this->addPublishableNode($rootNode);
        $this->addEnabledComponentsNode($rootNode);
        $this->addUserNode($rootNode);

        return $treeBuilder;
    }

    private function addPublishableNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('publishable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('permission')
                            ->cannotBeEmpty()
                            ->isRequired()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addEnabledComponentsNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('enabled_components')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('form')->defaultValue(true)->end()
                        ->booleanNode('collection')->defaultValue(true)->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addUserNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('user')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class_name')->isRequired()->end()
                        ->arrayNode('email_verification')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('email')
                                    ->children()
                                        ->scalarNode('redirect_path_query')->end()
                                        ->scalarNode('default_redirect_path')->isRequired()->end()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Please verify your email')->end()
                                    ->end()
                                ->end()
                                ->booleanNode('default_value')->isRequired()->end()
                                ->booleanNode('verify_on_change')->isRequired()->end()
                                ->booleanNode('verify_on_register')->isRequired()->end()
                                ->booleanNode('deny_unverified_login')->isRequired()->end()
                            ->end()
                        ->end()
                        ->arrayNode('new_email_confirmation')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('email')
                                    ->children()
                                        ->scalarNode('redirect_path_query')->end()
                                        ->scalarNode('default_redirect_path')->isRequired()->end()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Please confirm your new email address')->end()
                                    ->end()
                                ->end()
                                ->integerNode('request_timeout_seconds')->defaultValue(86400)->end()
                            ->end()
                        ->end()
                        ->arrayNode('password_reset')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('email')
                                    ->children()
                                        ->scalarNode('redirect_path_query')->end()
                                        ->scalarNode('default_redirect_path')->isRequired()->end()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Your password has been reset')->end()
                                    ->end()
                                ->end()
                                ->integerNode('repeat_ttl_seconds')->defaultValue(8600)->end()
                                ->integerNode('request_timeout_seconds')->defaultValue(3600)->end()
                            ->end()
                        ->end()
                        ->arrayNode('emails')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('welcome')
                                    ->canBeDisabled()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Welcome to {{ website_name }}')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('user_enabled')
                                    ->canBeDisabled()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Your account has been enabled')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('username_changed')
                                    ->canBeDisabled()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Your username has been updated')->end()
                                    ->end()
                                ->end()
                                ->arrayNode('password_changed')
                                    ->canBeDisabled()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Your password has been changed')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
