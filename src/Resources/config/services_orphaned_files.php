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
use Silverback\ApiComponentsBundle\Command\ScanOrphanedFilesCommand;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedFileDeletionStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedFileScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedFileReportStateProvider;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDeleter;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileLister;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileNameMatcher;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedFilesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedFilesHandler;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services
        ->set('silverback.api_components.orphaned_file.report_store')
        ->class(OrphanedFileReportStore::class)
        ->autoconfigure(false)
        ->args([
            new Reference(ManagerRegistry::class),
        ]);
    $services->alias(OrphanedFileReportStore::class, 'silverback.api_components.orphaned_file.report_store');

    $services
        ->set('silverback.api_components.orphaned_file.lister')
        ->class(StoredFileLister::class)
        ->autoconfigure(false);
    $services->alias(StoredFileLister::class, 'silverback.api_components.orphaned_file.lister');

    $services
        ->set('silverback.api_components.orphaned_file.name_matcher')
        ->class(StoredFileNameMatcher::class)
        ->autoconfigure(false);
    $services->alias(StoredFileNameMatcher::class, 'silverback.api_components.orphaned_file.name_matcher');

    $services
        ->set('silverback.api_components.orphaned_file.detector')
        ->class(OrphanedFileDetector::class)
        ->autoconfigure(false)
        ->args([
            '$registry' => new Reference(ManagerRegistry::class),
            '$uploadableAttributeReader' => new Reference('silverback.api_components.attribute_reader.uploadable'),
            '$filesystemProvider' => new Reference(FilesystemProvider::class),
            '$iriConverter' => new Reference(IriConverterInterface::class),
            '$lister' => new Reference('silverback.api_components.orphaned_file.lister'),
            '$nameMatcher' => new Reference('silverback.api_components.orphaned_file.name_matcher'),
            '$fileInfoRepository' => new Reference(FileInfoRepository::class),
            '$cacheResolvers' => tagged_iterator('liip_imagine.cache.resolver'),
            '$excludedPaths' => [],
            '$minimumAge' => 3600,
        ]);
    $services->alias(OrphanedFileDetector::class, 'silverback.api_components.orphaned_file.detector');

    $services
        ->set('silverback.api_components.orphaned_file.deleter')
        ->class(OrphanedFileDeleter::class)
        ->autoconfigure(false)
        ->args([
            new Reference('silverback.api_components.orphaned_file.detector'),
            new Reference(UploadableFileManager::class),
            new Reference(FilesystemProvider::class),
            new Reference('silverback.api_components.orphaned_file.report_store'),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
    $services->alias(OrphanedFileDeleter::class, 'silverback.api_components.orphaned_file.deleter');

    $services
        ->set('silverback.api_components.message_handler.scan_orphaned_files')
        ->class(ScanOrphanedFilesHandler::class)
        ->autoconfigure(false)
        ->args([
            new Reference('silverback.api_components.orphaned_file.detector'),
            new Reference('silverback.api_components.orphaned_file.report_store'),
        ])
        ->tag('messenger.message_handler', ['handles' => ScanOrphanedFilesMessage::class]);
    $services->alias(ScanOrphanedFilesHandler::class, 'silverback.api_components.message_handler.scan_orphaned_files');

    $services
        ->set(OrphanedFileScanStateProcessor::class)
        ->args([
            new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('silverback.api_components.message_handler.scan_orphaned_files'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.orphaned_file_scan', OrphanedFileScanStateProcessor::class);

    $services
        ->set(OrphanedFileReportStateProvider::class)
        ->args([
            new Reference('silverback.api_components.orphaned_file.report_store'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_provider');
    $services->alias('silverback.api_components.api_platform.state_provider.orphaned_file_report', OrphanedFileReportStateProvider::class);

    $services
        ->set(OrphanedFileDeletionStateProcessor::class)
        ->args([
            new Reference('silverback.api_components.orphaned_file.deleter'),
        ])
        ->autoconfigure(false)
        ->tag('api_platform.state_processor');
    $services->alias('silverback.api_components.api_platform.state_processor.orphaned_file_deletion', OrphanedFileDeletionStateProcessor::class);

    $services
        ->set('silverback.api_components.command.scan_orphaned_files')
        ->class(ScanOrphanedFilesCommand::class)
        ->autoconfigure(false)
        ->args([
            new Reference('silverback.api_components.message_handler.scan_orphaned_files'),
        ])
        ->tag('console.command', ['command' => ScanOrphanedFilesCommand::NAME]);
    $services->alias(ScanOrphanedFilesCommand::class, 'silverback.api_components.command.scan_orphaned_files');
};
