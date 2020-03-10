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
use ApiPlatform\Core\PathResolver\OperationPathResolverInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Command\FormCachePurgeCommand;
use Silverback\ApiComponentBundle\Doctrine\Extension\TablePrefixExtension;
use Silverback\ApiComponentBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentBundle\Repository\Core\RouteRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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
        ->set(RouteRepository::class)
        ->args([ref(ManagerRegistry::class)]);

    $services
        ->set(LayoutRepository::class)
        ->args([ref(ManagerRegistry::class)]);

    $services
        ->set(FormCachePurgeCommand::class)
        ->tag('console.command');

    $services->alias(ContextAwareCollectionDataProviderInterface::class, 'api_platform.collection_data_provider');
    $services->alias(Environment::class, 'twig');
    $services->alias(RoleHierarchy::class, 'security.role_hierarchy');
    $services->alias(OperationPathResolverInterface::class, 'api_platform.operation_path_resolver.router');

    $services
        ->set(TablePrefixExtension::class)
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services->alias(SlugifyInterface::class, 'slugify');
};
