<?php

namespace Silverback\ApiComponentBundle\Tests\config;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $services = $container->services();
    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();
    $services
        ->set(TestHandler::class)
        ->tag('silverback_api_component.form_handler');
    $services
        ->alias('test.logger', LoggerInterface::class)
        ->public();

    $services
        ->set(TestType::class);

    // To test it has been called from behat - not required in actual application
    $services
        ->set(TestHandler::class)
        ->public();
};
