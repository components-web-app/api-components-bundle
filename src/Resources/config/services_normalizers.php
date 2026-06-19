<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Resources\config;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\AbstractResourceNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\CollectionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentGroupNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentPositionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\DataUriNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PageDataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PersistedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ResourceManifestNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\RouteChildrenNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\RouteNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\TimestampedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UploadableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UserNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components_bundle.open_api.factory')
        ->autoconfigure(false)
        ->class(OpenApiFactory::class)
        ->decorate('api_platform.openapi.factory')
        ->args([
            new Reference('silverback.api_components_bundle.open_api.factory.inner'),
            new Reference('api_platform.metadata.resource.metadata_collection_factory'),
        ]);

    $services
        ->set('silverback.api_components.serializer.normalizer.abstract_resource')
        ->class(AbstractResourceNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(ApiResourceRouteFinder::class),
                new Reference(IriConverterInterface::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(AbstractResourceNormalizer::class, 'silverback.api_components.serializer.normalizer.abstract_resource');

    $services
        ->set('silverback.api_components.serializer.normalizer.collection')
        ->class(CollectionNormalizer::class)
        ->autoconfigure(false)
        ->args([
            new Reference(ResourceMetadataProvider::class),
        ])
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(CollectionNormalizer::class, 'silverback.api_components.serializer.normalizer.collection');

    $services
        ->set('silverback.api_components.serializer.normalizer.component_group')
        ->class(ComponentGroupNormalizer::class)
        ->autoconfigure(false)
        ->args([new Reference('api_platform.iri_converter')])
        ->tag('serializer.normalizer', ['priority' => -498]);
    $services->alias(ComponentGroupNormalizer::class, 'silverback.api_components.serializer.normalizer.component_group');

    $services
        ->set('silverback.api_components.serializer.normalizer.component_position')
        ->class(ComponentPositionNormalizer::class)
        ->autoconfigure(false)
        ->args([
            new Reference(PageDataProvider::class),
            new Reference('silverback.helper.component_position_sort_value'),
            new Reference(RequestStack::class),
            new Reference(PublishableStatusChecker::class),
            new Reference(ManagerRegistry::class),
            new Reference('api_platform.iri_converter'),
            new Reference(ResourceMetadataProvider::class),
        ])
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(ComponentPositionNormalizer::class, 'silverback.api_components.serializer.normalizer.component_position');

    $services
        ->set('silverback.api_components.serializer.normalizer.data_uri')
        ->class(DataUriNormalizer::class)
        ->decorate('serializer.normalizer.data_uri')
        ->autoconfigure(false)
        ->args(
            [
                new Reference('silverback.api_components.serializer.normalizer.data_uri.inner'),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(DataUriNormalizer::class, 'silverback.api_components.serializer.normalizer.data_uri');

    $services
        ->set('silverback.api_components.serializer.normalizer.metadata')
        ->class(MetadataNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                '', // set in dependency injection
                new Reference(ResourceMetadataProvider::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -500]);
    $services->alias(MetadataNormalizer::class, 'silverback.api_components.serializer.normalizer.metadata');

    $services
        ->set('silverback.api_components.serializer.normalizer.persisted')
        ->class(PersistedNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(EntityManagerInterface::class),
                new Reference(ResourceClassResolverInterface::class),
                new Reference(ResourceMetadataProvider::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(PersistedNormalizer::class, 'silverback.api_components.serializer.normalizer.persisted');

    $services
        ->set('silverback.api_components.serializer.normalizer.publishable')
        ->class(PublishableNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(PublishableStatusChecker::class),
                new Reference('doctrine'),
                new Reference('request_stack'),
                new Reference('api_platform.validator'),
                new Reference(IriConverterInterface::class),
                new Reference(UploadableFileManager::class),
                new Reference(ResourceMetadataProvider::class),
                new Reference(EventDispatcherInterface::class),
            ]
        )->tag('serializer.normalizer', ['priority' => -400]);
    $services->alias(PublishableNormalizer::class, 'silverback.api_components.serializer.normalizer.publishable');

    $services
        ->set('silverback.api_components.serializer.normalizer.page_data')
        ->class(PageDataNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference('silverback.metadata_factory.page_data'),
                new Reference(ResourceMetadataProvider::class),
            ]
        )->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(PageDataNormalizer::class, 'silverback.api_components.serializer.normalizer.page_data');

    $services
        ->set('silverback.api_components.serializer.normalizer.resource_manifest')
        ->class(ResourceManifestNormalizer::class)
        ->autoconfigure(false)
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(ResourceManifestNormalizer::class, 'silverback.api_components.serializer.normalizer.resource_manifest');

    $services
        ->set('silverback.api_components.serializer.normalizer.route_children')
        ->class(RouteChildrenNormalizer::class)
        ->autoconfigure(false)
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(RouteChildrenNormalizer::class, 'silverback.api_components.serializer.normalizer.route_children');

    $services
        ->set('silverback.api_components.serializer.normalizer.route')
        ->class(RouteNormalizer::class)
        ->autoconfigure(false)
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(RouteNormalizer::class, 'silverback.api_components.serializer.normalizer.route');

    $services
        ->set('silverback.api_components.serializer.normalizer.timestamped')
        ->class(TimestampedNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(ManagerRegistry::class),
                new Reference(TimestampedAttributeReader::class),
                new Reference(TimestampedDataPersister::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(TimestampedNormalizer::class, 'silverback.api_components.serializer.normalizer.timestamped');

    $services
        ->set('silverback.api_components.serializer.normalizer.uploadable')
        ->class(UploadableNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(MediaObjectFactory::class),
                new Reference(UploadableAttributeReader::class),
                new Reference(UploadableFileManager::class),
                new Reference(ManagerRegistry::class),
                new Reference(ResourceMetadataProvider::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(UploadableNormalizer::class, 'silverback.api_components.serializer.normalizer.uploadable');

    $services
        ->set('silverback.api_components.serializer.normalizer.user')
        ->class(UserNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                new Reference(UserDataProcessor::class),
                new Reference(RoleHierarchyInterface::class),
                new Reference(ResourceMetadataProvider::class),
                new Reference(MercureAuthorization::class),
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -499]);
    $services->alias(UserNormalizer::class, 'silverback.api_components.serializer.normalizer.user');
};
