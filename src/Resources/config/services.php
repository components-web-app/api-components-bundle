<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Resources\config;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Silverback\ApiComponentBundle\Event\PreNormalizeEvent;
use Silverback\ApiComponentBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentBundle\EventListener\PreNormalizeListener;
use Silverback\ApiComponentBundle\Form\Cache\FormCachePurger;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentBundle\Serializer\DataTransformer\CollectionTransformer;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Twig\Environment;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

/*
 * @author Daniel West <daniel@silverback.is>
 */
return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autoconfigure()
        ->private()
        // ->bind('$projectDir', '%kernel.project_dir%')
;

    $services
        ->set(CollectionTransformer::class);

    $services
        ->set(FormCachePurgeCommand::class)
        ->tag('console.command')
        ->args([
            new Reference(FormCachePurger::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services
        ->set(FormCachePurger::class)
        ->args([
            new Reference(EntityManagerInterface::class),
            new Reference(EventDispatcherInterface::class),
        ]);

    $services
        ->set(FormTypeClassValidator::class)
        ->tag('validator.constraint_validator')
        ->args(
            [
                '$formTypes' => new TaggedIteratorArgument('silverback_api_component.form_type'),
            ]
        );

    $services
        ->set(LayoutRepository::class)
        ->args([ref(ManagerRegistry::class)]);

    $services
        ->set(PreNormalizeListener::class)
        ->tag('kernel.event_listener', ['event' => PreNormalizeEvent::class])
        ->tag('container.service_subscriber');

    $services
        ->set(RouteRepository::class)
        ->args([ref(ManagerRegistry::class)]);

    $services
        ->set(TablePrefixExtension::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services
        ->set(TimestampedListener::class)
        ->args([new Reference(EntityManagerInterface::class)])
        ->tag('kernel.event_listener', ['event' => ViewEvent::class, 'priority' => EventPriorities::PRE_VALIDATE]);

    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
    $services->alias(Environment::class, 'twig');
    $services->alias(OperationPathResolverInterface::class, 'api_platform.operation_path_resolver.router');
    $services->alias(RoleHierarchy::class, 'security.role_hierarchy');
    $services->alias(SlugifyInterface::class, 'slugify');
};
