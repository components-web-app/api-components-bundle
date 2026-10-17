<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * @author Daniel West <daniel@silverback.is>
 */

use Silverback\ApiComponentsBundle\Command\PurgeHttpCacheCommand;
use Silverback\ApiComponentsBundle\Command\PurgeRenderedHtmlCommand;
use Silverback\ApiComponentsBundle\Command\WarmHttpCacheCommand;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\HttpCachePurgeStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RenderedHtmlPurgeStateProcessor;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\HttpCache\ManifestKeyResolver;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmDispatcher;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmer;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;
use Silverback\ApiComponentsBundle\MessageHandler\WarmHttpCacheHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('api_platform.http_cache.tag_collector')
        ->class(CwaTagCollector::class)
        ->autoconfigure(false);
    $services->alias('silverback.api_components.http_cache.tag_collector', 'api_platform.http_cache.tag_collector');
    $services->alias(CwaTagCollector::class, 'api_platform.http_cache.tag_collector');

    $services
        ->set('silverback.api_components.http_cache.manifest_key_resolver')
        ->class(ManifestKeyResolver::class)
        ->autoconfigure(false)
        ->args([
            new Reference('api_platform.iri_converter'),
        ]);
    $services->alias(ManifestKeyResolver::class, 'silverback.api_components.http_cache.manifest_key_resolver');

    $services
        ->set('silverback.api_components.http_cache.purger')
        ->class(HttpCachePurger::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference('api_platform.resource_class_resolver'),
            new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(CwaCollectorData::class),
            [],
            new Reference('silverback.api_components.http_cache.manifest_key_resolver'),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->tag('silverback_api_components.resource_changed_propagator')
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(HttpCachePurger::class, 'silverback.api_components.http_cache.purger');

    $services
        ->set('silverback.api_components.command.purge_rendered_html')
        ->class(PurgeRenderedHtmlCommand::class)
        ->tag('console.command')
        ->args([
            new Reference('silverback.api_components.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(PurgeRenderedHtmlCommand::class, 'silverback.api_components.command.purge_rendered_html');

    $services
        ->set(RenderedHtmlPurgeStateProcessor::class)
        ->args([
            new Reference('silverback.api_components.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.rendered_html_purge', RenderedHtmlPurgeStateProcessor::class);

    $services
        ->set('silverback.api_components.http_cache.flusher')
        ->class(HttpCacheFlusher::class)
        ->autoconfigure(false)
        ->args([
            new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '$invalidationClients' => [],
        ]);
    $services->alias(HttpCacheFlusher::class, 'silverback.api_components.http_cache.flusher');

    $services
        ->set('silverback.api_components.command.purge_http_cache')
        ->class(PurgeHttpCacheCommand::class)
        ->tag('console.command')
        ->args([
            new Reference('silverback.api_components.http_cache.flusher'),
            new Reference('silverback.api_components.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(PurgeHttpCacheCommand::class, 'silverback.api_components.command.purge_http_cache');

    $services
        ->set('silverback.api_components.http_cache.warmer')
        ->class(HttpCacheWarmer::class)
        ->autoconfigure(false)
        ->args([
            new Reference('http_client'),
            new Reference('api_platform.iri_converter'),
            new Reference('clock'),
        ]);
    $services->alias(HttpCacheWarmer::class, 'silverback.api_components.http_cache.warmer');

    $services
        ->set('silverback.api_components.http_cache.public_route_paths')
        ->class(PublicRoutePaths::class)
        ->autoconfigure(false)
        ->args([
            new Reference('doctrine'),
            new Reference('silverback.api_components.doctrine.orm.extension.route'),
        ]);
    $services->alias(PublicRoutePaths::class, 'silverback.api_components.http_cache.public_route_paths');

    $services
        ->set('silverback.api_components.command.warm_http_cache')
        ->class(WarmHttpCacheCommand::class)
        ->tag('console.command')
        ->args([
            new Reference('silverback.api_components.http_cache.public_route_paths'),
            new Reference('silverback.api_components.http_cache.warmer'),
        ]);
    $services->alias(WarmHttpCacheCommand::class, 'silverback.api_components.command.warm_http_cache');

    $services
        ->set('silverback.api_components.http_cache.warm_dispatcher')
        ->class(HttpCacheWarmDispatcher::class)
        ->autoconfigure(false)
        ->args([
            new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(HttpCacheWarmDispatcher::class, 'silverback.api_components.http_cache.warm_dispatcher');

    $services
        ->set('silverback.api_components.message_handler.warm_http_cache')
        ->class(WarmHttpCacheHandler::class)
        ->autoconfigure(false)
        ->args([
            '$publicRoutePaths' => new Reference('silverback.api_components.http_cache.public_route_paths'),
            '$httpCacheWarmer' => new Reference('silverback.api_components.http_cache.warmer'),
            '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->tag('messenger.message_handler', ['handles' => WarmHttpCacheMessage::class]);
    $services->alias(WarmHttpCacheHandler::class, 'silverback.api_components.message_handler.warm_http_cache');

    $services
        ->set(HttpCachePurgeStateProcessor::class)
        ->args([
            new Reference('silverback.api_components.http_cache.flusher'),
            new Reference('silverback.api_components.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.http_cache_purge', HttpCachePurgeStateProcessor::class);
};
