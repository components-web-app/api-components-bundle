<?php

namespace Silverback\ApiComponentBundle\Tests\config;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $services = $container->services();
    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private()
        ->bind('$projectDirectory', '%kernel.project_dir%')
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\TestBundle\\', '../../TestBundle/*')
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\TestBundle\\DataFixtures\\', '../../TestBundle/DataFixtures')
        ->tag('doctrine.fixture.orm')
        ->public()
    ;
    $services
        ->set(TestHandler::class)
        ->tag('silverback_api_component.form_handler')
    ;
    $services
        ->alias('test.logger', LoggerInterface::class)
        ->public()
    ;
};
