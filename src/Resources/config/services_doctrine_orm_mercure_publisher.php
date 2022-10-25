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
use Silverback\ApiComponentsBundle\ApiPlatform\Api\MercureIriConverter;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishMercureUpdatesListener;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubRegistry;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.event_listener.doctrine.mercure_publish_listener')
        ->class(PublishMercureUpdatesListener::class)
        ->args([
            new Reference('api_platform.iri_converter'),
            new Reference(ManagerRegistry::class),
            new Reference('silverback.api_components.mercure.resource_publisher'),
            new Reference('api_platform.resource_class_resolver'),
        ])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::onFlush])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::preUpdate])
        ->tag('doctrine.event_listener', ['event' => DoctrineEvents::postFlush]);
    $services->alias(PublishMercureUpdatesListener::class, 'silverback.api_components.event_listener.doctrine.mercure_publish_listener');

    $services
        ->set('silverback.api_components.mercure.resource_publisher')
        ->class(MercureResourcePublisher::class)
        ->args([
            new Reference(HubRegistry::class),
            new Reference(MercureIriConverter::class),
            new Reference('api_platform.metadata.resource.metadata_collection_factory'),
            new Reference('api_platform.resource_class_resolver'),
            '%api_platform.formats%',
            new Reference('messenger.default_bus', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference('api_platform.graphql.subscription.subscription_manager', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference('api_platform.graphql.subscription.mercure_iri_generator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ])
        ->call('setSerializer', [new Reference('serializer')]);
    $services->alias(MercureResourcePublisher::class, 'silverback.api_components.mercure.resource_publisher');
};
