<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use Cocur\Slugify\SlugifyInterface;
use GuzzleHttp\Client;
use Liip\ImagineBundle\Binary\Loader\FileSystemLoader;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentBundle\EventListener\Doctrine\EntitySubscriber;
use Silverback\ApiComponentBundle\EventListener\Doctrine\RouteAwareSubscriber;
use Silverback\ApiComponentBundle\Repository\RouteRepository;
use Silverback\ApiComponentBundle\Serializer\ApiContextBuilder;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Swagger\SwaggerDecorator;
use Silverback\ApiComponentBundle\Validator\Constraints\ComponentLocationValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormHandlerClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\LinkValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private()
        ->bind('$formHandlers', new TaggedIteratorArgument('silverback_api_component.form_handler'))
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\', '../../*')
        ->exclude('../../{Entity,Migrations,Tests,Resources}')
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\EntityListener\\', '../../EntityListener')
        ->tag('doctrine.orm.entity_listener')
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\Controller\\', '../../Controller')
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(FormHandlerClassValidator::class)
        ->tag('validator.constraint_validator')
    ;

    $services
        ->set(ComponentLocationValidator::class)
        ->tag('validator.constraint_validator')
    ;

    $services
        ->set(LinkValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$routeRepository' => RouteRepository::class
            ]
        )
    ;

    $services
        ->set(EntitySubscriber::class)
        ->tag('doctrine.event_subscriber')
    ;

    $services
        ->set(RouteAwareSubscriber::class)
        ->tag('doctrine.event_subscriber')
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\DataProvider\\Item\\', '../../DataProvider/Item')
        ->tag('api_platform.item_data_provider', ['priority' => 1])
        ->autoconfigure(false)
    ;

    $services
        ->set(SwaggerDecorator::class)
        ->decorate('api_platform.swagger.normalizer.documentation')
        ->autoconfigure(false)
        ->args(
            [
                new Reference(SwaggerDecorator::class . '.inner')
            ]
        )
    ;

    $services
        ->set(FormTypeClassValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$formTypes' => new TaggedIteratorArgument('silverback_api_component.form_type')
            ]
        )
    ;

    $services
        ->set(ApiNormalizer::class)
        ->decorate('api_platform.jsonld.normalizer.item')
        ->args([
            '$decorated' => new Reference(ApiNormalizer::class . '.inner'), // 'api_platform.serializer.normalizer.item'
            '$projectDir' => '%kernel.project_dir%'
        ])
    ;

    $services
        ->set(ApiContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([new Reference(ApiContextBuilder::class . '.inner')])
    ;

    $services->set(Client::class); // create guzzle client as a service
    $services->alias(SlugifyInterface::class, 'slugify');
    $services->alias(FileSystemLoader::class, 'liip_imagine.binary.loader.default');
    $services->alias(FilterService::class, 'liip_imagine.service.filter');
    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
};
