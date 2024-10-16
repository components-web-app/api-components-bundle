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

use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.http_cache.purger')
        ->class(HttpCachePurger::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference('api_platform.resource_class_resolver'),
            new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])
        ->tag('silverback_api_components.resource_changed_propagator');
    $services->alias(HttpCachePurger::class, 'silverback.api_components.http_cache.purger');
};
