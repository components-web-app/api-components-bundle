<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Post;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileDeletion;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
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
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile\InMemoryOrphanedFileReportStore;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OrphanedFileServicesTest extends TestCase
{
    private const string BUS_ID = 'messenger.default_bus';
    private const string DETECTOR_ID = 'silverback.api_components.orphaned_file.detector';
    private const string DELETER_ID = 'silverback.api_components.orphaned_file.deleter';
    private const string LISTER_ID = 'silverback.api_components.orphaned_file.lister';
    private const string NAME_MATCHER_ID = 'silverback.api_components.orphaned_file.name_matcher';
    private const string HANDLER_ID = 'silverback.api_components.message_handler.scan_orphaned_files';
    private const string STORE_ID = 'silverback.api_components.orphaned_file.report_store';
    private const string COMMAND_ID = 'silverback.api_components.command.scan_orphaned_files';

    public function test_the_report_is_stored_through_doctrine(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::STORE_ID);

        self::assertSame(OrphanedFileReportStore::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([ManagerRegistry::class], array_map('strval', $definition->getArguments()));
        self::assertSame(self::STORE_ID, (string) $container->getAlias(OrphanedFileReportStore::class));
    }

    public function test_the_detector_is_wired_to_the_uploadable_fields_the_filesystems_and_every_imagine_cache_resolver(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::DETECTOR_ID);

        self::assertSame(OrphanedFileDetector::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(ManagerRegistry::class, (string) $definition->getArgument('$registry'));
        self::assertSame('silverback.api_components.attribute_reader.uploadable', (string) $definition->getArgument('$uploadableAttributeReader'));
        self::assertSame(FilesystemProvider::class, (string) $definition->getArgument('$filesystemProvider'));
        self::assertSame(IriConverterInterface::class, (string) $definition->getArgument('$iriConverter'));
        self::assertSame(self::LISTER_ID, (string) $definition->getArgument('$lister'));
        self::assertSame(self::NAME_MATCHER_ID, (string) $definition->getArgument('$nameMatcher'));
        self::assertSame(FileInfoRepository::class, (string) $definition->getArgument('$fileInfoRepository'));
        self::assertSame(StoredFileNameMatcher::class, $container->getDefinition(self::NAME_MATCHER_ID)->getClass());
        self::assertFalse($container->getDefinition(self::NAME_MATCHER_ID)->isAutoconfigured());
        self::assertSame(self::NAME_MATCHER_ID, (string) $container->getAlias(StoredFileNameMatcher::class));
        $resolvers = $definition->getArgument('$cacheResolvers');
        self::assertInstanceOf(TaggedIteratorArgument::class, $resolvers);
        self::assertSame('liip_imagine.cache.resolver', $resolvers->getTag());
        self::assertSame([], $definition->getArgument('$excludedPaths'));
        self::assertSame(3600, $definition->getArgument('$minimumAge'));
        self::assertSame(self::DETECTOR_ID, (string) $container->getAlias(OrphanedFileDetector::class));
        self::assertSame(StoredFileLister::class, $container->getDefinition(self::LISTER_ID)->getClass());
        self::assertFalse($container->getDefinition(self::LISTER_ID)->isAutoconfigured());
        self::assertSame(self::LISTER_ID, (string) $container->getAlias(StoredFileLister::class));
    }

    public function test_the_deleter_deletes_through_the_uploadable_file_manager_and_logs_optionally(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::DELETER_ID);

        self::assertSame(OrphanedFileDeleter::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [self::DETECTOR_ID, UploadableFileManager::class, FilesystemProvider::class, self::STORE_ID, 'logger'],
            array_map('strval', $definition->getArguments())
        );
        $logger = $definition->getArgument(4);
        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
        self::assertSame(self::DELETER_ID, (string) $container->getAlias(OrphanedFileDeleter::class));
    }

    public function test_the_handler_handles_the_scan_message_without_relying_on_autoconfiguration(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::HANDLER_ID);

        self::assertSame(ScanOrphanedFilesHandler::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([['handles' => ScanOrphanedFilesMessage::class]], $definition->getTag('messenger.message_handler'));
        self::assertSame([self::DETECTOR_ID, self::STORE_ID], array_map('strval', $definition->getArguments()));
        self::assertSame(self::HANDLER_ID, (string) $container->getAlias(ScanOrphanedFilesHandler::class));
    }

    public function test_the_processors_and_provider_keep_their_class_names_as_service_ids(): void
    {
        $container = $this->loadContainer(false);

        foreach ([OrphanedFileScanStateProcessor::class, OrphanedFileDeletionStateProcessor::class] as $id) {
            self::assertTrue($container->getDefinition($id)->hasTag('api_platform.state_processor'));
            self::assertFalse($container->getDefinition($id)->isAutoconfigured());
        }
        self::assertTrue($container->getDefinition(OrphanedFileReportStateProvider::class)->hasTag('api_platform.state_provider'));
        self::assertFalse($container->getDefinition(OrphanedFileReportStateProvider::class)->isAutoconfigured());
        self::assertSame(OrphanedFileScanStateProcessor::class, (string) $container->getAlias('silverback.api_components.api_platform.state_processor.orphaned_file_scan'));
        self::assertSame(OrphanedFileDeletionStateProcessor::class, (string) $container->getAlias('silverback.api_components.api_platform.state_processor.orphaned_file_deletion'));
        self::assertSame(OrphanedFileReportStateProvider::class, (string) $container->getAlias('silverback.api_components.api_platform.state_provider.orphaned_file_report'));
        self::assertSame([self::DELETER_ID], array_map('strval', $container->getDefinition(OrphanedFileDeletionStateProcessor::class)->getArguments()));
    }

    public function test_the_scan_processor_dispatches_the_scan_message_on_the_default_bus(): void
    {
        $container = $this->loadContainer();
        $detector = $this->createMock(OrphanedFileDetector::class);
        $detector->expects(self::never())->method('detect');
        $container->set(self::DETECTOR_ID, $detector);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ScanOrphanedFilesMessage::class))
            ->willReturnCallback(static fn (object $message) => new Envelope($message));
        $container->set(self::BUS_ID, $bus);

        self::assertNull($container->get(OrphanedFileScanStateProcessor::class)->process(null, new Post()));
        self::assertNull($container->get(OrphanedFileReportStore::class)->fetch());
    }

    public function test_the_scan_processor_runs_the_scan_itself_when_there_is_no_message_bus(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning([['adapter' => 'local', 'path' => 'a.png']]));

        self::assertNull($container->get(OrphanedFileScanStateProcessor::class)->process(null, new Post()));

        self::assertSame([['adapter' => 'local', 'path' => 'a.png']], $container->get(OrphanedFileReportStore::class)->fetch()?->orphanedFiles);
    }

    public function test_the_handler_stores_the_detected_report(): void
    {
        $container = $this->loadContainer();
        $report = new OrphanedFileReport(new \DateTimeImmutable(), [['adapter' => 'local', 'path' => 'b.png']]);
        $detector = $this->createStub(OrphanedFileDetector::class);
        $detector->method('detect')->willReturn($report);
        $container->set(self::DETECTOR_ID, $detector);

        $container->get(self::HANDLER_ID)(new ScanOrphanedFilesMessage());

        self::assertSame($report, $container->get(OrphanedFileReportStore::class)->fetch());
        self::assertSame($report, $container->get(ScanOrphanedFilesHandler::class)->scan());
    }

    public function test_the_provider_returns_the_stored_report_and_null_when_there_is_none(): void
    {
        $container = $this->loadContainer();
        $provider = $container->get(OrphanedFileReportStateProvider::class);

        self::assertNull($provider->provide(new Get()));

        $report = new OrphanedFileReport(new \DateTimeImmutable(), [], [['resource' => '/dummy_uploadables/1', 'adapter' => 'local', 'path' => 'm.png']]);
        $container->get(OrphanedFileReportStore::class)->save($report);

        self::assertSame($report, $provider->provide(new Get()));
    }

    public function test_the_deletion_processor_deletes_the_selected_paths(): void
    {
        $container = $this->loadContainer();
        $result = new OrphanedFileDeletion([['adapter' => 'local', 'path' => 'a.png']]);
        $deleter = $this->createMock(OrphanedFileDeleter::class);
        $deleter->expects(self::once())->method('delete')->with(['a.png'])->willReturn($result);
        $container->set(self::DELETER_ID, $deleter);
        $request = new OrphanedFileDeletion();
        $request->paths = ['a.png'];

        self::assertSame($result, $container->get(OrphanedFileDeletionStateProcessor::class)->process($request, new Post()));
    }

    public function test_the_deletion_processor_deletes_every_orphaned_file_when_all_is_requested(): void
    {
        $container = $this->loadContainer();
        $deleter = $this->createMock(OrphanedFileDeleter::class);
        $deleter->expects(self::once())->method('delete')->with(null)->willReturn(new OrphanedFileDeletion());
        $container->set(self::DELETER_ID, $deleter);
        $request = new OrphanedFileDeletion();
        $request->all = true;
        $request->paths = ['ignored.png'];

        $container->get(OrphanedFileDeletionStateProcessor::class)->process($request, new Post());
    }

    public function test_the_deletion_processor_refuses_anything_but_a_deletion_request(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DELETER_ID, $this->createStub(OrphanedFileDeleter::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(OrphanedFileDeletion::class);
        $container->get(OrphanedFileDeletionStateProcessor::class)->process(null, new Post());
    }

    public function test_the_scan_command_is_registered_under_its_name(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::COMMAND_ID);

        self::assertSame(ScanOrphanedFilesCommand::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([['command' => 'silverback:api-components:scan-orphaned-files']], $definition->getTag('console.command'));
        self::assertSame([self::HANDLER_ID], array_map('strval', $definition->getArguments()));
        self::assertSame(self::COMMAND_ID, (string) $container->getAlias(ScanOrphanedFilesCommand::class));
    }

    public function test_the_scan_command_stores_the_report_and_prints_the_counts(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(
            [['adapter' => 'local', 'path' => 'a.png'], ['adapter' => 's3', 'path' => 'b.png']],
            [['resource' => '/dummy_uploadables/1', 'adapter' => 'local', 'path' => 'm.png']],
            [['adapter' => 'local', 'path' => 'logo.png']],
        ));
        $tester = new CommandTester($container->get(ScanOrphanedFilesCommand::class));

        self::assertSame(0, $tester->execute([]));

        self::assertSame("Orphaned files: 2\nUnknown files: 1\nMissing files: 1\n", $tester->getDisplay());
        self::assertCount(2, $container->get(OrphanedFileReportStore::class)->fetch()?->orphanedFiles ?? []);
    }

    public function test_the_scan_command_lists_the_paths_when_verbose(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(
            [['adapter' => 'local', 'path' => 'a.png']],
            [['resource' => '/dummy_uploadables/1', 'adapter' => 'local', 'path' => 'm.png']],
            [['adapter' => 'local', 'path' => 'logo.png']],
        ));
        $tester = new CommandTester($container->get(ScanOrphanedFilesCommand::class));

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame("Orphaned files: 1\n  local: a.png\nUnknown files: 1\n  local: logo.png\nMissing files: 1\n  local: m.png (/dummy_uploadables/1)\n", $tester->getDisplay());
    }

    public function test_the_scan_command_describes_itself(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning([]));
        $command = $container->get(ScanOrphanedFilesCommand::class);

        self::assertSame('silverback:api-components:scan-orphaned-files', $command->getName());
        self::assertStringContainsString('never deletes', $command->getDescription());
    }

    /**
     * @param list<array{adapter: string, path: string}>                   $orphanedFiles
     * @param list<array{resource: string, adapter: string, path: string}> $missingFiles
     * @param list<array{adapter: string, path: string}>                   $unknownFiles
     */
    private function detectorReturning(array $orphanedFiles, array $missingFiles = [], array $unknownFiles = []): OrphanedFileDetector
    {
        $detector = $this->createStub(OrphanedFileDetector::class);
        $detector->method('detect')->willReturn(new OrphanedFileReport(new \DateTimeImmutable(), $orphanedFiles, $missingFiles, $unknownFiles));

        return $detector;
    }

    private function loadContainer(bool $withTestDoubles = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_orphaned_files.php');
        (new ResolveClassPass())->process($container);
        if ($withTestDoubles) {
            $container->set(self::STORE_ID, new InMemoryOrphanedFileReportStore());
        }

        return $container;
    }
}
