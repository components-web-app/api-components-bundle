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

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentsBundle\ApiPlatform\Api\MercureIriConverter;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubRegistry;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.mercure.resource_publisher')
        ->class(MercureResourcePublisher::class)
        ->args([
            new Reference(HubRegistry::class),
            new Reference(MercureIriConverter::class),
            new Reference(SerializerContextBuilderInterface::class),
            new Reference('request_stack'),
            '%api_platform.formats%',
            new Reference('api_platform.metadata.resource.metadata_collection_factory'),
            new Reference('api_platform.resource_class_resolver'),
            new Reference('messenger.default_bus', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference('api_platform.graphql.subscription.subscription_manager', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference('api_platform.graphql.subscription.mercure_iri_generator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ])
        ->call('setSerializer', [new Reference('serializer')])
        ->tag('silverback_api_components.resource_changed_propagator');
    $services->alias(MercureResourcePublisher::class, 'silverback.api_components.mercure.resource_publisher');
};
