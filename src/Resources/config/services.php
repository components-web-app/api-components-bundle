<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use Cocur\Slugify\SlugifyInterface;
use GuzzleHttp\Client;
use Liip\ImagineBundle\Async\ResolveCacheProcessor;
use Silverback\ApiComponentBundle\Controller\FormSubmitPost;
use Silverback\ApiComponentBundle\EventListener\Doctrine\EntitySubscriber;
use Silverback\ApiComponentBundle\Serializer\ApiContextBuilder;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Swagger\SwaggerDecorator;
use Silverback\ApiComponentBundle\Validator\Constraints\ComponentLocationValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormHandlerClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
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
        ->load('Silverback\\ApiComponentBundle\\DataProvider\\Item\\', '../../DataProvider/Item')
        ->tag('api_platform.item_data_provider', [ 'priority' => 1 ])
        ->autoconfigure(false)
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\Controller\\', '../../Controller')
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(FormSubmitPost::class)
        ->tag('controller.service_arguments')
        ->args(
            [
                '$formHandlers' => new TaggedIteratorArgument('silverback_api_component.form_handler')
            ]
        )
    ;

    $services
        ->alias(SlugifyInterface::class, 'slugify')
    ;

    $services
        ->set(SwaggerDecorator::class)
        ->decorate('api_platform.swagger.normalizer.documentation')
        ->args(
            [
                new Reference(SwaggerDecorator::class . '.inner')
            ]
        )
        ->autoconfigure(false)
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
        ->set(FormHandlerClassValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$formHandlers' => new TaggedIteratorArgument('silverback_api_component.form_handler')
            ]
        )
    ;

    $services
        ->set(ComponentLocationValidator::class)
        ->tag('validator.constraint_validator')
    ;

    $services
        ->set(ApiNormalizer::class)
        ->decorate('api_platform.jsonld.normalizer.item')
        ->args([
            new Reference(ApiNormalizer::class . '.inner'),
            '%kernel.project_dir%'
        ])
    ;

    $services
        ->set(ApiContextBuilder::class)
        ->decorate('api_platform.serializer.context_builder')
        ->args([ new Reference(ApiContextBuilder::class . '.inner') ])
    ;

    $services
        ->set(EntitySubscriber::class)
        ->tag('doctrine.event_subscriber')
    ;

    $services
        ->set('liip_imagine.async.resolve_cache_processor')
        ->class(ResolveCacheProcessor::class)
        ->args(
            [
                new Reference('liip_imagine.filter.manager'),
                new Reference('liip_imagine.service.filter'),
                new Reference('enqueue.producer')
            ]
        )
        ->tag('enqueue.client.processor')
        ->public()
    ;

    $services->set(Client::class);
};
