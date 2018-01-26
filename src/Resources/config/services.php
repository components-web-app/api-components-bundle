<?php

namespace Silverback\ApiComponentBundle\Resources\config;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Controller\FormSubmitPost;
use Silverback\ApiComponentBundle\DataFixtures\Component\ContentComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureColumnsComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureStackedComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FeatureTextListComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\FormComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\GalleryComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\HeroComponent;
use Silverback\ApiComponentBundle\DataFixtures\Component\NewsComponent;
use Silverback\ApiComponentBundle\DataFixtures\ComponentServiceLocator;
use Silverback\ApiComponentBundle\DataFixtures\Page\AbstractPage;
use Silverback\ApiComponentBundle\Swagger\SwaggerDecorator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormHandlerClassValidator;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

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
                ContentComponent::class => new Reference(ContentComponent::class),
                FeatureColumnsComponent::class => new Reference(FeatureColumnsComponent::class),
                FeatureTextListComponent::class => new Reference(FeatureTextListComponent::class),
                FeatureStackedComponent::class => new Reference(FeatureStackedComponent::class),
                FormComponent::class => new Reference(FormComponent::class),
                GalleryComponent::class => new Reference(GalleryComponent::class),
                HeroComponent::class => new Reference(HeroComponent::class),
                NewsComponent::class => new Reference(NewsComponent::class)
            ]
        ])
    ;

    $services
        ->load('Silverback\\ApiComponentBundle\\DataFixtures\\Component\\', '../../DataFixtures/Component')
        ->call('load', [new Reference(ObjectManager::class)])
    ;
};
