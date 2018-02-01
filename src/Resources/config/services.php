<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use Cocur\Slugify\SlugifyInterface;
use Liip\ImagineBundle\Async\ResolveCacheProcessor;
use Silverback\ApiComponentBundle\Controller\FormSubmitPost;
use Silverback\ApiComponentBundle\EventListener\FileEntitySubscriber;
use Silverback\ApiComponentBundle\Factory\Component\ContentFactory;
use Silverback\ApiComponentBundle\Factory\Component\FeatureColumnsFactory;
use Silverback\ApiComponentBundle\Factory\Component\FeatureStackedFactory;
use Silverback\ApiComponentBundle\Factory\Component\FeatureTextListFactory;
use Silverback\ApiComponentBundle\Factory\Component\FormFactory;
use Silverback\ApiComponentBundle\Factory\Component\GalleryFactory;
use Silverback\ApiComponentBundle\Factory\Component\HeroFactory;
use Silverback\ApiComponentBundle\Factory\Component\NewsFactory;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\Serializer\ApiContextBuilder;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Swagger\SwaggerDecorator;
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
        ->args(
            [
                '$formHandlers' => new TaggedIteratorArgument('silverback_api_component.form_handler')
            ]
        )
        ->tag('controller.service_arguments')
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
        ->set(ComponentServiceLocator::class)
        ->tag('container.service_locator')
        ->args([
            [
                ContentFactory::class => new Reference(ContentFactory::class),
                FeatureColumnsFactory::class => new Reference(FeatureColumnsFactory::class),
                FeatureTextListFactory::class => new Reference(FeatureTextListFactory::class),
                FeatureStackedFactory::class => new Reference(FeatureStackedFactory::class),
                FormFactory::class => new Reference(FormFactory::class),
                GalleryFactory::class => new Reference(GalleryFactory::class),
                HeroFactory::class => new Reference(HeroFactory::class),
                NewsFactory::class => new Reference(NewsFactory::class)
            ]
        ])
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
        ->set(FileEntitySubscriber::class)
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
};
