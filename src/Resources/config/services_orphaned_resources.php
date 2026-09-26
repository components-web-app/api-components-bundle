<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Command\ScanOrphanedCommand;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceDeletionStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedResourceReportStateProvider;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDeleter;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotifier;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.orphaned_resource.report_store')
        ->class(OrphanedResourceReportStore::class)
        ->autoconfigure(false)
        ->args([
            new Reference(ManagerRegistry::class),
        ]);
    $services->alias(OrphanedResourceReportStore::class, 'silverback.api_components.orphaned_resource.report_store');

    $services
        ->set('silverback.api_components.orphaned_resource.detector')
        ->class(OrphanedResourceDetector::class)
        ->autoconfigure(false)
        ->args([
            new Reference(ManagerRegistry::class),
            new Reference('silverback.api_components.attribute_reader.publishable'),
            new Reference(IriConverterInterface::class),
        ]);
    $services->alias(OrphanedResourceDetector::class, 'silverback.api_components.orphaned_resource.detector');

    $services
        ->set('silverback.api_components.message_handler.scan_orphaned_resources')
        ->class(ScanOrphanedResourcesHandler::class)
        ->autoconfigure(false)
        ->args([
            new Reference('silverback.api_components.orphaned_resource.detector'),
            new Reference('silverback.api_components.orphaned_resource.report_store'),
        ])
        ->tag('messenger.message_handler', ['handles' => ScanOrphanedResourcesMessage::class]);
    $services->alias(ScanOrphanedResourcesHandler::class, 'silverback.api_components.message_handler.scan_orphaned_resources');

    $services
        ->set(OrphanedResourceScanStateProcessor::class)
        ->args([
            new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('silverback.api_components.message_handler.scan_orphaned_resources'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.orphaned_resource_scan', OrphanedResourceScanStateProcessor::class);

    $services
        ->set(OrphanedResourceReportStateProvider::class)
        ->args([
            new Reference('silverback.api_components.orphaned_resource.report_store'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.orphaned_resource_report', OrphanedResourceReportStateProvider::class);

    $services
        ->set('silverback.api_components.orphaned_resource.deleter')
        ->class(OrphanedResourceDeleter::class)
        ->autoconfigure(false)
        ->args([
            new Reference(ManagerRegistry::class),
            new Reference('silverback.api_components.orphaned_resource.detector'),
            new Reference('silverback.helper.orphaned_resource_helper'),
            new Reference(IriConverterInterface::class),
        ]);
    $services->alias(OrphanedResourceDeleter::class, 'silverback.api_components.orphaned_resource.deleter');

    $services
        ->set(OrphanedResourceDeletionStateProcessor::class)
        ->args([
            new Reference('silverback.api_components.orphaned_resource.deleter'),
            new Reference('silverback.api_components.message_handler.scan_orphaned_resources'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.orphaned_resource_deletion', OrphanedResourceDeletionStateProcessor::class);

    $services
        ->set('silverback.api_components.factory.orphaned_resource.changed_email')
        ->class(OrphanedResourcesChangedEmailFactory::class)
        ->autoconfigure(false)
        ->args([
            '$twig' => new Reference('twig'),
            '$subject' => 'Orphaned resources changed on {{ website_name }}',
            '$context' => [],
        ]);
    $services->alias(OrphanedResourcesChangedEmailFactory::class, 'silverback.api_components.factory.orphaned_resource.changed_email');

    $services
        ->set('silverback.api_components.orphaned_resource.notifier')
        ->class(OrphanedResourceNotifier::class)
        ->autoconfigure(false)
        ->args([
            '$mailer' => new Reference(MailerInterface::class),
            '$emailFactory' => new Reference('silverback.api_components.factory.orphaned_resource.changed_email'),
            '$urlResolver' => new Reference(RefererUrlResolver::class),
            '$recipients' => [],
            '$adminPagePath' => '/_cwa/orphaned',
            '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(OrphanedResourceNotifier::class, 'silverback.api_components.orphaned_resource.notifier');

    $services
        ->set('silverback.api_components.command.scan_orphaned')
        ->class(ScanOrphanedCommand::class)
        ->autoconfigure(false)
        ->args([
            new Reference('silverback.api_components.message_handler.scan_orphaned_resources'),
            new Reference('silverback.api_components.orphaned_resource.notifier'),
        ])
        ->tag('console.command', ['command' => ScanOrphanedCommand::NAME])
        ->tag('console.command', ['command' => ScanOrphanedCommand::LEGACY_NAME]);
    $services->alias(ScanOrphanedCommand::class, 'silverback.api_components.command.scan_orphaned');
};
