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

namespace Silverback\ApiComponentsBundle\Resources\config;

use Doctrine\ORM\Events as DoctrineEvents;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PurgeHttpCacheListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/*
 * @author Daniel West <daniel@silverback.is>
 */
return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.event_listener.doctrine.purge_http_cache_listener', PurgeHttpCacheListener::class)
        ->args([
            new Reference('api_platform.http_cache.purger'),
            new Reference('api_platform.iri_converter'),
        ])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::onFlush]);

    // Todo: Remove alias when https://github.com/api-platform/core/pull/4695 is merged.
    $services
        ->alias('api_platform.http_cache.purger', 'api_platform.http_cache.purger.varnish.xkey');
};
