<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use Cocur\Slugify\SlugifyInterface;
use Silverback\ApiComponentBundle\Swagger\SwaggerDecorator;
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
        ->load('Silverback\\ApiComponentBundle\\Controller\\', '../../Controller')
        ->tag('controller.service_arguments')
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\EntityListener\\', '../../EntityListener')
        ->tag('doctrine.orm.entity_listener')
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\DataProvider\\', '../../DataProvider/*')
        ->tag('api_platform.item_data_provider', [ 'priority' => 1 ])
        ->autoconfigure(false)
    ;

    $services
        ->alias(SlugifyInterface::class, 'slugify')
    ;

    $services
        ->set(SwaggerDecorator::class)
        ->decorate('api_platform.swagger.normalizer.documentation')
        ->args(
            [
                new Reference('@ApiComponent\Swagger\SwaggerDecorator.inner')
            ]
        )
        ->autoconfigure(false)
    ;
};
