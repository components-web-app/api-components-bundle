<?php

namespace Silverback\ApiComponentBundle\Tests\config;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\ContentFixture;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $services = $container->services();
    $services
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private()
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\TestBundle\\', '../../TestBundle/*')
    ;
    $services
        ->load('Silverback\\ApiComponentBundle\\Tests\\TestBundle\\DataFixtures\\', '../../TestBundle/DataFixtures')
        ->tag('doctrine.fixture.orm')
    ;

    $services
        ->set(ContentFixture::class)
        ->args([
               '$projectDirectory' => '%kernel.project_dir%'
           ])
    ;

    $services
        ->set(TestHandler::class)
        ->tag('silverback_api_component.form_handler')
    ;
    $services
        ->alias('test.logger', LoggerInterface::class)
        ->public()
    ;

    $services->alias('test.' . FormFactory::class, FormFactory::class)->public();
};
