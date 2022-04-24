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

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\ResourceClassResolverInterface;
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
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\AbstractResourceNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\CollectionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentPositionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\DataUriNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\MetadataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PageDataNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PersistedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\RouteNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\TimestampedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UploadableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\UserNormalizer;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

//    $services
//        ->set('silverback.api_components_bundle.open_api.factory')
//        ->autoconfigure(false)
//        ->class(OpenApiFactory::class)
//        ->decorate('api_platform.openapi.factory')
//        ->args([
//            new Reference('silverback.api_components_bundle.open_api.factory.inner'),
//            new Reference('api_platform.metadata.resource.metadata_factory.short_name'),
//        ]);
//
//    $services
//        ->set(AbstractResourceNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(ApiResourceRouteFinder::class),
//                new Reference(IriConverterInterface::class),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(CollectionNormalizer::class)
//        ->autoconfigure(false)
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(ComponentPositionNormalizer::class)
//        ->autoconfigure(false)
//        ->args([
//            new Reference(PageDataProvider::class),
//            new Reference('silverback.helper.component_position_sort_value'),
//            new Reference(RequestStack::class),
//            new Reference(PublishableStatusChecker::class),
//            new Reference(ManagerRegistry::class),
//            new Reference('api_platform.iri_converter'),
//        ])
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(DataUriNormalizer::class)
//        ->decorate('serializer.normalizer.data_uri')
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(DataUriNormalizer::class . '.inner'),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);

    $services
        ->set(MetadataNormalizer::class)
        ->autoconfigure(false)
        ->args(
            [
                '', // set in dependency injection
            ]
        )
        ->tag('serializer.normalizer', ['priority' => -500]);

//    $services
//        ->set(PersistedNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(EntityManagerInterface::class),
//                new Reference(ResourceClassResolverInterface::class),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(PublishableNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(PublishableStatusChecker::class),
//                new Reference('doctrine'),
//                new Reference('request_stack'),
//                new Reference('api_platform.validator'),
//                new Reference(IriConverterInterface::class),
//                new Reference(UploadableFileManager::class),
//                new Reference('silverback.api_components.event_listener.doctrine.purge_http_cache_listener', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
//            ]
//        )->tag('serializer.normalizer', ['priority' => -400]);
//
//    $services
//        ->set(PageDataNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference('silverback.metadata_factory.page_data'),
//            ]
//        )->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(RouteNormalizer::class)
//        ->autoconfigure(false)
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(TimestampedNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(ManagerRegistry::class),
//                new Reference(TimestampedAttributeReader::class),
//                new Reference(TimestampedDataPersister::class),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(UploadableNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(MediaObjectFactory::class),
//                new Reference(UploadableAttributeReader::class),
//                new Reference(ManagerRegistry::class),
//                new Reference(UploadableFileManager::class),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);
//
//    $services
//        ->set(UserNormalizer::class)
//        ->autoconfigure(false)
//        ->args(
//            [
//                new Reference(UserDataProcessor::class),
//                new Reference(RoleHierarchyInterface::class),
//            ]
//        )
//        ->tag('serializer.normalizer', ['priority' => -499]);
};
