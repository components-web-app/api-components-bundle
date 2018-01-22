<?php

namespace Silverback\ApiComponentBundle\Tests\config;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container)
{
    $services = $container->services();
    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private()
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\src\\', '../src/*')
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\src\\DataFixtures\\', '../src/DataFixtures')
        ->tag('doctrine.fixture.orm')
    ;
    $services
        ->alias('test.logger', LoggerInterface::class)
        ->public()
    ;
};
