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
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;
use Silverback\ApiComponentsBundle\Utility\EmailRecipientList;
use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImage;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException as MimeInvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class Configuration implements ConfigurationInterface
{
    private const string DOCTRINE_REFRESH_TOKEN_STORAGE = 'silverback.api_components.refresh_token.storage.doctrine';

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
        $this->addOrphanedResourcesNode($rootNode);
        $this->addOrphanedFilesNode($rootNode);
        $this->addImagineNode($rootNode);
        $this->addUploadableNode($rootNode);
        $this->addFixturesNode($rootNode);

        return $treeBuilder;
    }

    private function addFixturesNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('fixtures')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('assets')
                            ->info('Where generate-fixtures stores uploaded files by content hash and where a scaffold loads them from. Unset, generate-fixtures writes readable copies beside the scaffold.')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('store')
                                    ->info('local (a folder) or flysystem (one of the application\'s filesystem adapters).')
                                    ->defaultValue(FixtureAssetStoreFactory::LOCAL)
                                    ->validate()
                                        ->ifTrue(static fn (mixed $store): bool => !\in_array($store, [FixtureAssetStoreFactory::LOCAL, FixtureAssetStoreFactory::FLYSYSTEM], true) && !str_contains((string) $store, '%'))
                                        ->thenInvalid(\sprintf('The fixture asset store %%s is not one of "%s" or "%s".', FixtureAssetStoreFactory::LOCAL, FixtureAssetStoreFactory::FLYSYSTEM))
                                    ->end()
                                ->end()
                                ->scalarNode('path')
                                    ->info('The local store\'s folder. Null is an assets folder beside the scaffold class.')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('adapter')
                                    ->info('The flysystem store\'s filesystem adapter alias.')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('prefix')
                                    ->info('The flysystem store\'s folder within the adapter.')
                                    ->defaultValue('')
                                ->end()
                            ->end()
                            ->validate()
                                ->ifTrue(static fn (array $assets): bool => FixtureAssetStoreFactory::FLYSYSTEM === $assets['store'] && \in_array($assets['adapter'], [null, ''], true))
                                ->thenInvalid('The flysystem fixture asset store needs an adapter.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addHttpCacheNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('http_cache')
                    ->addDefaultsIfNotSet()
                    ->info('Cache-safety headers for responses that vary by the authenticated session, and the resource classes whose writes invalidate the front end\'s rendered HTML.')
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
                        ->arrayNode('scheduled_expiry_resource_classes')
                            ->info('Resource classes whose anonymous GET responses have `s-maxage` and `max-age` capped to the soonest future route go-live date, so a shared cache cannot serve them past a scheduled publication.')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                Route::class,
                                RoutableInterface::class,
                                ResourceManifest::class,
                            ])
                        ->end()
                        ->arrayNode('purge_rendered_html_classes')
                            ->info('Resource classes whose writes also purge the `' . HttpCachePurger::RENDERED_HTML_TAG . '` surrogate key, dropping every cached page the front end has rendered. Purging this key drops the whole HTML cache at once, so keep the list short and deliberate, and list only leaf resources that are not reachable as an association of another resource.')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                SiteConfigParameter::class,
                            ])
                        ->end()
                        ->arrayNode('warm_after_flush')
                            ->info('After a successful flush of the whole HTTP cache (the purge endpoint with no body, `purge-http-cache` with no options, a fixture load), dispatch a bounded warm of the public routes, shortest first, once with `Accept-Encoding: gzip` and once with `br`, as `' . WarmHttpCacheMessage::class . '` messages on `messenger.default_bus`. Route that message to an async transport: without one the warm runs inside the request or command that flushed.')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('base_url')
                                    ->info('The URL of the HTTP cache in front of the API, as `warm-http-cache --base-url`.')
                                    ->defaultValue('http://localhost')
                                    ->validate()
                                        ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || !preg_match('{\Ahttps?://[^/]}i', $value))
                                        ->thenInvalid('The warm base URL must be an http or https URL, got %s.')
                                    ->end()
                                ->end()
                                ->floatNode('max_time')
                                    ->beforeNormalization()->ifTrue(static fn (mixed $value): bool => \is_int($value))->then(static fn (int $value): float => (float) $value)->end()
                                    ->info('Seconds after which each warm stops starting requests.')
                                    ->min(0.001)
                                    ->defaultValue(60.0)
                                ->end()
                                ->integerNode('concurrency')
                                    ->info('The most warm requests in flight at once.')
                                    ->min(1)
                                    ->defaultValue(2)
                                ->end()
                                ->floatNode('timeout')
                                    ->beforeNormalization()->ifTrue(static fn (mixed $value): bool => \is_int($value))->then(static fn (int $value): float => (float) $value)->end()
                                    ->info('The most seconds one warm request may take.')
                                    ->min(0.001)
                                    ->defaultValue(30.0)
                                ->end()
                                ->integerNode('limit')
                                    ->info('Warm at most this many routes, shortest paths first; null warms every public route until the maximum time.')
                                    ->min(1)
                                    ->defaultNull()
                                ->end()
                                ->integerNode('requests_per_connection')
                                    ->info('Send `Connection: close` on every Nth warm request, as `warm-http-cache --requests-per-connection`, so a Kubernetes Service, which balances per connection, spreads the warm across its pods; 1 opens a new connection for every request; null keeps connections alive.')
                                    ->min(1)
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addOrphanedResourcesNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('orphaned_resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('notify')
                            ->addDefaultsIfNotSet()
                            ->info('Emails sent by `silverback:api-components:scan-orphaned` when its result differs from the last stored report. No recipients means no emails.')
                            ->children()
                                ->variableNode('recipients')
                                    ->info('Email addresses, bare or `Name <address>`: a list, or one comma-separated string so that an environment variable can supply them. Quote a name containing a comma.')
                                    ->defaultValue([])
                                    ->beforeNormalization()
                                        ->ifNull()
                                        ->thenEmptyArray()
                                    ->end()
                                    ->validate()
                                        ->ifTrue(static fn (mixed $recipients): bool => !self::areEmailAddresses($recipients))
                                        ->thenInvalid('The orphaned resources notification recipients %s must be email addresses.')
                                    ->end()
                                ->end()
                                ->scalarNode('admin_page_path')
                                    ->info('The front-end path of the admin page listing orphaned resources, linked from the email on the `user.email_links.default_origin`.')
                                    ->cannotBeEmpty()
                                    ->defaultValue('/_cwa/orphaned')
                                ->end()
                                ->scalarNode('subject')->cannotBeEmpty()->defaultValue('Orphaned resources changed on {{ website_name }}')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addOrphanedFilesNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('orphaned_files')
                    ->addDefaultsIfNotSet()
                    ->info('The orphaned files report: stored files under an uploadable field\'s filesystem and prefix that no uploadable row references.')
                    ->children()
                        ->integerNode('minimum_age')
                            ->info('Seconds since a file was last modified before it can be reported. An upload is stored before its row is flushed, so a younger file may be one being uploaded now.')
                            ->min(0)
                            ->defaultValue(3600)
                        ->end()
                        ->arrayNode('excluded_paths')
                            ->info('Path prefixes never listed or reported on any scanned filesystem, for anything else the application stores beside its uploads. Imagine cache prefixes are excluded without being listed here.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addImagineNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('imagine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('memory_limit')
                            ->info('The PHP memory limit an imagine filter is generated under, raised only for the generation and restored after it, in PHP shorthand (e.g. "512M"). A filter whose estimated cost does not fit under it is skipped and logged. A higher memory_limit is kept; null generates under the current limit.')
                            ->defaultValue('320M')
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || null === PhpMemoryLimit::parse($value)))
                                ->thenInvalid('The imagine memory_limit must be a positive size in PHP shorthand such as "512M", or null; got %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addUploadableNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('uploadable')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('cache_control')
                            ->addDefaultsIfNotSet()
                            ->info('The Cache-Control written on stored files, on Google Cloud Storage and S3 adapters only. A field overrides either value with `cacheControl:` or `imagineCacheControl:` on its #[UploadableField], and `false` there writes none. null writes none.')
                            ->children()
                                ->append($this->cacheControlNode('original', 'public, max-age=31536000, immutable', 'For an uploaded file. A stored name is never reused, so it can be immutable.'))
                                ->append($this->cacheControlNode('imagine', 'public, max-age=2592000', 'For an imagine variant, which is rewritten at the same path when its filter is regenerated.'))
                            ->end()
                        ->end()
                        ->scalarNode('image_max_pixels')
                            ->info('The pixel limit of a #[UploadedImage] constraint that sets no maxPixels, on upload and in the orphaned files report. 30 megapixels fits the default imagine memory_limit. null sets no limit.')
                            ->defaultValue(UploadedImage::DEFAULT_MAX_PIXELS)
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_int($value) || $value < 1))
                                ->thenInvalid('The uploaded image pixel limit must be a positive integer, or null for none; got %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function cacheControlNode(string $name, string $default, string $info): NodeDefinition
    {
        return (new ScalarNodeDefinition($name))
            ->info($info)
            ->defaultValue($default)
            ->validate()
                ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || '' === trim($value)))
                ->thenInvalid('A Cache-Control must be a header value such as "public, max-age=3600", or null for none; got %s.')
            ->end();
    }

    private static function areEmailAddresses(mixed $recipients): bool
    {
        if (\is_string($recipients)) {
            $recipients = EmailRecipientList::split($recipients);
        }
        if (!\is_array($recipients)) {
            return false;
        }
        foreach ($recipients as $recipient) {
            if (!\is_string($recipient)) {
                return false;
            }
            $recipient = trim($recipient);
            if ('' !== $recipient && !str_contains($recipient, '%') && !self::isEmailAddress($recipient)) {
                return false;
            }
        }

        return true;
    }

    private static function isEmailAddress(string $value): bool
    {
        try {
            Address::create($value);
        } catch (RfcComplianceException|MimeInvalidArgumentException) {
            return false;
        }

        return true;
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
                    ->isRequired()
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
                    ->validate()
                        ->ifTrue(static fn (array $v): bool => self::DOCTRINE_REFRESH_TOKEN_STORAGE === $v['handler_id'] && empty($v['options']['class']))
                        ->thenInvalid('"silverback_api_components.refresh_token.options.class" must name your RefreshToken entity when using the doctrine storage handler.')
                    ->end()
                ->end()
            ->end();
    }

    private function addPublishableNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('publishable')
                    ->isRequired()
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
                    ->isRequired()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('class_name')
                            ->cannotBeEmpty()
                            ->isRequired()
                        ->end()
                        ->arrayNode('email_verification')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
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
                                ->integerNode('repeat_ttl_seconds')->defaultValue(300)->end()
                            ->end()
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
                                ->integerNode('repeat_ttl_seconds')->defaultValue(300)->end()
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
                        ->arrayNode('email_links')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('allowed_origins')
                                    ->defaultValue([])
                                    ->scalarPrototype()
                                        ->validate()
                                            ->ifTrue(static fn (string $pattern): bool => !str_contains($pattern, '%') && false === @preg_match(RefererUrlResolver::allowedOriginRegex($pattern), ''))
                                            ->thenInvalid('The allowed origin pattern %s for links in emails is not a valid regular expression.')
                                        ->end()
                                    ->end()
                                ->end()
                                ->scalarNode('default_origin')
                                    ->defaultNull()
                                    ->validate()
                                        ->ifTrue(static fn (string $origin): bool => '' !== $origin && !str_contains($origin, '%') && null === RefererUrlResolver::normaliseOrigin($origin))
                                        ->thenInvalid('The default origin %s for links in emails must be a scheme and host with an optional port.')
                                    ->end()
                                ->end()
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
