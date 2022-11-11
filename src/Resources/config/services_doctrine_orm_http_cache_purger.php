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

/*
 * @author Daniel West <daniel@silverback.is>
 */

use Doctrine\ORM\Events as DoctrineEvents;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PurgeHttpCacheListener;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.event_listener.doctrine.purge_http_cache_listener')
        ->class(PurgeHttpCacheListener::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference(ManagerRegistry::class),
            new Reference('silverback.api_components.http_cache.purger'),
            new Reference('api_platform.resource_class_resolver'),
        ])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::onFlush])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postFlush]);
    $services->alias(PurgeHttpCacheListener::class, 'silverback.api_components.event_listener.doctrine.purge_http_cache_listener');

    $services
        ->set('silverback.api_components.http_cache.purger')
        ->class(HttpCachePurger::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference('api_platform.resource_class_resolver'),
            new Reference('api_platform.http_cache.purger'),
        ]);
    $services->alias(HttpCachePurger::class, 'silverback.api_components.http_cache.purger');
};
