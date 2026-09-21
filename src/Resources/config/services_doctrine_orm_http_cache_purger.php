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

use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\HttpCache\ManifestKeyResolver;
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
        ])
        ->tag('silverback_api_components.resource_changed_propagator')
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(HttpCachePurger::class, 'silverback.api_components.http_cache.purger');
};
