<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RealtimeEventStateProcessor;
use Silverback\ApiComponentsBundle\Helper\Realtime\LastWriteResolver;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.helper.realtime.user_resolver')
        ->class(RealtimeUserResolver::class)
        ->autoconfigure(false)
        ->args([
            new Reference('security.token_storage'),
            new Reference(IriConverterInterface::class),
        ]);
    $services->alias(RealtimeUserResolver::class, 'silverback.api_components.helper.realtime.user_resolver');

    $services
        ->set('silverback.api_components.helper.realtime.last_write_resolver')
        ->class(LastWriteResolver::class)
        ->autoconfigure(false)
        ->args([
            new Reference('request_stack'),
            new Reference('silverback.api_components.helper.realtime.user_resolver'),
        ]);
    $services->alias(LastWriteResolver::class, 'silverback.api_components.helper.realtime.last_write_resolver');

    $services
        ->set('silverback.api_components.realtime.rate_limiter_cache')
        ->parent('cache.app')
        ->tag('cache.pool');

    $services
        ->set('silverback.api_components.realtime.rate_limiter_storage')
        ->class(CacheStorage::class)
        ->autoconfigure(false)
        ->args([new Reference('silverback.api_components.realtime.rate_limiter_cache')]);

    $services
        ->set('silverback.api_components.realtime.rate_limiter')
        ->class(RateLimiterFactory::class)
        ->autoconfigure(false)
        ->args([
            RealtimeEventStateProcessor::RATE_LIMITER_CONFIG,
            new Reference('silverback.api_components.realtime.rate_limiter_storage'),
            null,
        ]);

    $services
        ->set(RealtimeEventStateProcessor::class)
        ->autoconfigure(false)
        ->args([
            '$mercureAuthorization' => new Reference(MercureAuthorization::class),
            '$hubRegistry' => new Reference(HubRegistry::class),
            '$rateLimiterFactory' => new Reference('silverback.api_components.realtime.rate_limiter'),
            '$userResolver' => new Reference('silverback.api_components.helper.realtime.user_resolver'),
            '$clock' => new Reference('clock'),
            '$hubName' => null,
            '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.realtime_event', RealtimeEventStateProcessor::class);
};
