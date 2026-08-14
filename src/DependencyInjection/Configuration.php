<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DependencyInjection;

use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Cookie;

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

        $this->addMercureNode($rootNode);
        $this->addRouteSecurityNode($rootNode);
        $this->addRoutableSecurityNode($rootNode);
        $this->addRefreshTokenNode($rootNode);
        $this->addPublishableNode($rootNode);
        $this->addEnabledComponentsNode($rootNode);
        $this->addUserNode($rootNode);
        $this->addHttpCacheNode($rootNode);

        return $treeBuilder;
    }

    private function addHttpCacheNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('http_cache')
                    ->addDefaultsIfNotSet()
                    ->info('Cache-safety headers for responses that vary by the authenticated session.')
                    ->children()
                        ->arrayNode('personalised_resource_classes')
                            ->info('Resource classes whose GET responses are marked `private, no-store` for authenticated users. Publishable resources are always treated as personalised in addition to this list.')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                Route::class,
                                ResourceManifest::class,
                                ComponentPosition::class,
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addMercureNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('mercure')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('hub_name')->defaultNull()->end()
                        ->booleanNode('secure_subscriptions')
                            ->defaultFalse()
                            ->info('When true, subscriber JWT tokens only include topics for resources the current user can access. Requires class-level security expressions (i.e. no "object" variable) on API operations to be evaluated at subscription time.')
                        ->end()
                        ->arrayNode('cookie')
                            ->addDefaultsIfNotSet()
                            ->children()
                                 ->scalarNode('samesite')->defaultValue(Cookie::SAMESITE_STRICT)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addRouteSecurityNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('route_security')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('route')->end()
                            ->scalarNode('security')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addRoutableSecurityNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->scalarNode('routable_security')->defaultNull()->end()
            ->end();
    }

    private function addRefreshTokenNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('refresh_token')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('handler_id')->cannotBeEmpty()->isRequired()->end()
                        ->arrayNode('options')
                            ->useAttributeAsKey('key')
                            ->prototype('variable')->end()
                        ->end()
                        ->scalarNode('cookie_name')->cannotBeEmpty()->isRequired()->end()
                        ->scalarNode('ttl')->cannotBeEmpty()->isRequired()->end()
                        ->scalarNode('database_user_provider')->cannotBeEmpty()->isRequired()->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addPublishableNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('publishable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('permission')->cannotBeEmpty()->isRequired()->end()
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
                        ->scalarNode('class_name')
                            ->isRequired()
                        ->end()
                        ->arrayNode('email_verification')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
                            // Every child resolves to a value. An `isRequired()` child under a node
                            // that carries a default is never enforced — ArrayNode::finalizeValue()
                            // inserts the default and skips finalisation for an omitted node — so
                            // the extension read keys that were not there. Defaults are all-off:
                            // an application that never mentions email verification must not start
                            // sending verification emails it has given no redirect target for, and
                            // must not have its users locked out of logging in.
                            ->children()
                                ->arrayNode('email')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('redirect_path_query')->defaultNull()->end()
                                        ->scalarNode('default_redirect_path')->defaultNull()->end()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Please verify your email')->end()
                                    ->end()
                                ->end()
                                ->booleanNode('default_value')->defaultFalse()->end()
                                ->booleanNode('verify_on_change')->defaultFalse()->end()
                                ->booleanNode('verify_on_register')->defaultFalse()->end()
                                ->booleanNode('deny_unverified_login')->defaultFalse()->end()
                            ->end()
                            // Only reachable when the node is present in the configuration, which is
                            // exactly when it can be wrong: asking for verification emails without
                            // telling the bundle where the link points can only fail later, inside
                            // AbstractUserEmailFactory::getTokenPath(), at the moment a user
                            // registers. Fail at compile time instead.
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => ($v['enabled'] ?? true)
                                    && ($v['verify_on_register'] || $v['verify_on_change'])
                                    && null === $v['email']['default_redirect_path']
                                    && null === $v['email']['redirect_path_query'])
                                ->thenInvalid('One of "silverback_api_components.user.email_verification.email.default_redirect_path" or "…email.redirect_path_query" must be set to send verification emails.')
                            ->end()
                        ->end()
                        ->arrayNode('new_email_confirmation')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('email')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('redirect_path_query')->defaultNull()->end()
                                        ->scalarNode('default_redirect_path')->defaultNull()->end()
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
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('redirect_path_query')->defaultNull()->end()
                                        ->scalarNode('default_redirect_path')->defaultNull()->end()
                                        ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Your password reset request')->end()
                                    ->end()
                                ->end()
                                ->integerNode('repeat_ttl_seconds')->defaultValue(86400)->end()
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
