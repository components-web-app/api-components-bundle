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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceDeletion;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Command\ScanOrphanedCommand;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceDeletionStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedResourceReportStateProvider;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDeleter;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotificationResult;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceNotifier;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportChange;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\InMemoryOrphanedResourceReportStore;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OrphanedResourceServicesTest extends TestCase
{
    private const string BUS_ID = 'messenger.default_bus';
    private const string DETECTOR_ID = 'silverback.api_components.orphaned_resource.detector';
    private const string HANDLER_ID = 'silverback.api_components.message_handler.scan_orphaned_resources';
    private const string STORE_ID = 'silverback.api_components.orphaned_resource.report_store';
    private const string NOTIFIER_ID = 'silverback.api_components.orphaned_resource.notifier';
    private const string EMAIL_FACTORY_ID = 'silverback.api_components.factory.orphaned_resource.changed_email';

    public function test_the_report_is_stored_through_doctrine(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::STORE_ID);

        self::assertSame(OrphanedResourceReportStore::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([ManagerRegistry::class], array_map('strval', $definition->getArguments()));
        self::assertSame(self::STORE_ID, (string) $container->getAlias(OrphanedResourceReportStore::class));
    }

    public function test_the_notifier_is_wired_to_the_mailer_the_email_factory_the_link_resolver_and_an_optional_logger(): void
    {
        $container = $this->loadContainer(false);
        $definition = $container->getDefinition(self::NOTIFIER_ID);

        self::assertSame(OrphanedResourceNotifier::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(MailerInterface::class, (string) $definition->getArgument('$mailer'));
        self::assertSame(self::EMAIL_FACTORY_ID, (string) $definition->getArgument('$emailFactory'));
        self::assertSame(RefererUrlResolver::class, (string) $definition->getArgument('$urlResolver'));
        self::assertSame([], $definition->getArgument('$recipients'));
        self::assertSame('/_cwa/orphaned', $definition->getArgument('$adminPagePath'));
        $logger = $definition->getArgument('$logger');
        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
        self::assertSame(self::NOTIFIER_ID, (string) $container->getAlias(OrphanedResourceNotifier::class));
    }

    public function test_the_email_factory_renders_with_twig(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition(self::EMAIL_FACTORY_ID);

        self::assertSame(OrphanedResourcesChangedEmailFactory::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame('twig', (string) $definition->getArgument('$twig'));
        self::assertSame('Orphaned resources changed on {{ website_name }}', $definition->getArgument('$subject'));
        self::assertSame(self::EMAIL_FACTORY_ID, (string) $container->getAlias(OrphanedResourcesChangedEmailFactory::class));
    }

    public function test_the_detector_is_wired_to_doctrine_the_publishable_reader_and_the_iri_converter(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition(self::DETECTOR_ID);

        self::assertSame(OrphanedResourceDetector::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [ManagerRegistry::class, 'silverback.api_components.attribute_reader.publishable', IriConverterInterface::class],
            array_map('strval', $definition->getArguments())
        );
        self::assertSame(self::DETECTOR_ID, (string) $container->getAlias(OrphanedResourceDetector::class));
    }

    public function test_the_handler_handles_the_scan_message_without_relying_on_autoconfiguration(): void
    {
        $definition = $this->loadContainer()->getDefinition(self::HANDLER_ID);

        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([['handles' => ScanOrphanedResourcesMessage::class]], $definition->getTag('messenger.message_handler'));
    }

    public function test_the_processor_and_provider_keep_their_class_names_as_service_ids(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->getDefinition(OrphanedResourceScanStateProcessor::class)->hasTag('api_platform.state_processor'));
        self::assertTrue($container->getDefinition(OrphanedResourceReportStateProvider::class)->hasTag('api_platform.state_provider'));
        self::assertSame(OrphanedResourceScanStateProcessor::class, (string) $container->getAlias('silverback.api_components.api_platform.state_processor.orphaned_resource_scan'));
        self::assertSame(OrphanedResourceReportStateProvider::class, (string) $container->getAlias('silverback.api_components.api_platform.state_provider.orphaned_resource_report'));
    }

    public function test_the_processor_dispatches_the_scan_message_on_the_default_bus(): void
    {
        $container = $this->loadContainer();
        $detector = $this->createMock(OrphanedResourceDetector::class);
        $detector->expects(self::never())->method('detect');
        $container->set(self::DETECTOR_ID, $detector);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ScanOrphanedResourcesMessage::class))
            ->willReturnCallback(static fn (object $message) => new Envelope($message));
        $container->set(self::BUS_ID, $bus);

        self::assertNull($container->get(OrphanedResourceScanStateProcessor::class)->process(null, new Post()));
    }

    public function test_the_processor_runs_the_scan_itself_when_there_is_no_message_bus(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));

        self::assertNull($container->get(OrphanedResourceScanStateProcessor::class)->process(null, new Post()));

        self::assertSame(['/_/component_groups/1'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
        self::assertNull($container->get(OrphanedResourceReportStore::class)->fetchNotified());
    }

    public function test_the_handler_stores_the_detected_report(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/2']));

        $container->get(self::HANDLER_ID)(new ScanOrphanedResourcesMessage());

        self::assertSame(['/_/component_groups/2'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_provider_returns_the_stored_report_and_null_when_there_is_none(): void
    {
        $container = $this->loadContainer();
        $provider = $container->get(OrphanedResourceReportStateProvider::class);

        self::assertNull($provider->provide(new Get()));

        $report = new OrphanedResourceReport(new \DateTimeImmutable(), [], [], ['/component/dummy_components/1']);
        $container->get(OrphanedResourceReportStore::class)->save($report);

        self::assertSame(['/component/dummy_components/1'], $provider->provide(new Get())?->components);
    }

    public function test_the_handler_class_is_the_one_registered(): void
    {
        self::assertSame(ScanOrphanedResourcesHandler::class, $this->loadContainer()->getDefinition(self::HANDLER_ID)->getClass());
    }

    public function test_the_scan_command_is_registered_under_its_name_and_the_clean_orphaned_alias(): void
    {
        $definition = $this->loadContainer()->getDefinition('silverback.api_components.command.scan_orphaned');

        self::assertSame(ScanOrphanedCommand::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [['command' => 'silverback:api-components:scan-orphaned'], ['command' => 'silverback:api-components:clean-orphaned']],
            $definition->getTag('console.command')
        );
        self::assertSame([self::HANDLER_ID, self::NOTIFIER_ID], array_map('strval', $definition->getArguments()));
    }

    public function test_the_scan_command_stores_the_report_and_prints_the_counts_per_kind(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1', '/_/component_groups/2']));
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        self::assertSame(0, $tester->execute([]));

        self::assertSame("Component groups: 2\nComponent positions: 0\nComponents: 0\n", $tester->getDisplay());
        self::assertSame(['/_/component_groups/1', '/_/component_groups/2'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_scan_command_lists_the_iris_when_verbose(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame("Component groups: 1\n  /_/component_groups/1\nComponent positions: 0\nComponents: 0\n", $tester->getDisplay());
    }

    public function test_the_scan_command_notifies_on_a_change_and_says_so(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createMock(OrphanedResourceNotifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(self::callback(static fn (OrphanedResourceReportChange $change) => null === $change->baseline && ['/_/component_groups/1'] === $change->report->componentGroups))
            ->willReturn(OrphanedResourceNotificationResult::Sent);
        $container->set(self::NOTIFIER_ID, $notifier);
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        self::assertSame(0, $tester->execute([]));

        self::assertStringEndsWith("Components: 0\nThe report has changed: a notification was sent.\n", $tester->getDisplay());
    }

    public function test_the_scan_command_compares_with_the_last_alert_not_the_stored_report(): void
    {
        $container = $this->loadContainer();
        $store = $container->get(OrphanedResourceReportStore::class);
        $store->markNotified(new OrphanedResourceReport(new \DateTimeImmutable(), ['/_/component_groups/9']));
        $store->save(new OrphanedResourceReport(new \DateTimeImmutable(), ['/_/component_groups/8']));
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createMock(OrphanedResourceNotifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(self::callback(static fn (OrphanedResourceReportChange $change) => ['/_/component_groups/9'] === $change->baseline?->componentGroups))
            ->willReturn(OrphanedResourceNotificationResult::Unchanged);
        $container->set(self::NOTIFIER_ID, $notifier);
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        self::assertSame(0, $tester->execute([]));

        self::assertStringEndsWith("Components: 0\nThe report has not changed: no notification was sent.\n", $tester->getDisplay());
        self::assertSame(['/_/component_groups/1'], $store->fetchNotified()?->componentGroups);
    }

    public function test_a_sent_notification_advances_the_last_alert(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createStub(OrphanedResourceNotifier::class);
        $notifier->method('notify')->willReturn(OrphanedResourceNotificationResult::Sent);
        $container->set(self::NOTIFIER_ID, $notifier);

        (new CommandTester($container->get(ScanOrphanedCommand::class)))->execute([]);

        self::assertSame(['/_/component_groups/1'], $container->get(OrphanedResourceReportStore::class)->fetchNotified()?->componentGroups);
    }

    public function test_no_notify_leaves_the_last_alert_alone(): void
    {
        $container = $this->loadContainer();
        $store = $container->get(OrphanedResourceReportStore::class);
        $store->markNotified(new OrphanedResourceReport(new \DateTimeImmutable(), ['/_/component_groups/9']));
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createMock(OrphanedResourceNotifier::class);
        $notifier->expects(self::never())->method('notify');
        $container->set(self::NOTIFIER_ID, $notifier);

        (new CommandTester($container->get(ScanOrphanedCommand::class)))->execute(['--no-notify' => true]);

        self::assertSame(['/_/component_groups/9'], $store->fetchNotified()?->componentGroups);
        self::assertSame(['/_/component_groups/1'], $store->fetch()?->componentGroups);
    }

    public function test_a_failed_notification_is_reported_but_the_command_succeeds_and_keeps_the_report(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createStub(OrphanedResourceNotifier::class);
        $notifier->method('notify')->willReturn(OrphanedResourceNotificationResult::Failed);
        $container->set(self::NOTIFIER_ID, $notifier);
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        self::assertSame(0, $tester->execute([]));

        self::assertStringEndsWith("Components: 0\nThe report has changed, but the notification could not be sent. The error has been logged.\n", $tester->getDisplay());
        self::assertSame(['/_/component_groups/1'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_scan_command_says_nothing_about_notifications_when_no_recipients_are_configured(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning([]));
        $notifier = $this->createStub(OrphanedResourceNotifier::class);
        $notifier->method('notify')->willReturn(OrphanedResourceNotificationResult::NoRecipients);
        $container->set(self::NOTIFIER_ID, $notifier);
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        $tester->execute([]);

        self::assertSame("Component groups: 0\nComponent positions: 0\nComponents: 0\n", $tester->getDisplay());
    }

    public function test_the_scan_command_does_not_notify_with_no_notify(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createMock(OrphanedResourceNotifier::class);
        $notifier->expects(self::never())->method('notify');
        $container->set(self::NOTIFIER_ID, $notifier);
        $tester = new CommandTester($container->get(ScanOrphanedCommand::class));

        self::assertSame(0, $tester->execute(['--no-notify' => true]));

        self::assertSame("Component groups: 1\nComponent positions: 0\nComponents: 0\n", $tester->getDisplay());
        self::assertSame(['/_/component_groups/1'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_http_scan_and_the_deletion_refresh_never_notify(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/1']));
        $notifier = $this->createMock(OrphanedResourceNotifier::class);
        $notifier->expects(self::never())->method('notify');
        $container->set(self::NOTIFIER_ID, $notifier);
        $deleter = $this->createStub(OrphanedResourceDeleter::class);
        $deleter->method('delete')->willReturn(new OrphanedResourceDeletion());
        $container->set('silverback.api_components.orphaned_resource.deleter', $deleter);
        $request = new OrphanedResourceDeletion();
        $request->all = true;

        $container->get(OrphanedResourceScanStateProcessor::class)->process(null, new Post());
        $container->get(OrphanedResourceDeletionStateProcessor::class)->process($request, new Post());

        self::assertSame(['/_/component_groups/1'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_deletion_processor_keeps_its_class_name_as_service_id_and_is_wired_explicitly(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition(OrphanedResourceDeletionStateProcessor::class);

        self::assertTrue($definition->hasTag('api_platform.state_processor'));
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(['silverback.api_components.orphaned_resource.deleter', self::HANDLER_ID], array_map('strval', $definition->getArguments()));
        self::assertSame(OrphanedResourceDeletionStateProcessor::class, (string) $container->getAlias('silverback.api_components.api_platform.state_processor.orphaned_resource_deletion'));
    }

    public function test_the_deleter_is_wired_explicitly(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition('silverback.api_components.orphaned_resource.deleter');

        self::assertSame(OrphanedResourceDeleter::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [ManagerRegistry::class, self::DETECTOR_ID, 'silverback.helper.orphaned_resource_helper', IriConverterInterface::class],
            array_map('strval', $definition->getArguments())
        );
        self::assertSame('silverback.api_components.orphaned_resource.deleter', (string) $container->getAlias(OrphanedResourceDeleter::class));
    }

    public function test_the_deletion_processor_deletes_the_selection_then_refreshes_the_stored_report(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning(['/_/component_groups/3']));
        $result = new OrphanedResourceDeletion(['componentGroups' => ['/_/component_groups/1'], 'componentPositions' => [], 'components' => []]);
        $deleter = $this->createMock(OrphanedResourceDeleter::class);
        $deleter->expects(self::once())->method('delete')->with(['/_/component_groups/1'])->willReturn($result);
        $container->set('silverback.api_components.orphaned_resource.deleter', $deleter);
        $request = new OrphanedResourceDeletion();
        $request->iris = ['/_/component_groups/1'];

        self::assertSame($result, $container->get(OrphanedResourceDeletionStateProcessor::class)->process($request, new Post()));
        self::assertSame(['/_/component_groups/3'], $container->get(OrphanedResourceReportStore::class)->fetch()?->componentGroups);
    }

    public function test_the_deletion_processor_deletes_every_orphan_when_all_is_requested(): void
    {
        $container = $this->loadContainer();
        $container->set(self::DETECTOR_ID, $this->detectorReturning([]));
        $deleter = $this->createMock(OrphanedResourceDeleter::class);
        $deleter->expects(self::once())->method('delete')->with(null)->willReturn(new OrphanedResourceDeletion());
        $container->set('silverback.api_components.orphaned_resource.deleter', $deleter);
        $request = new OrphanedResourceDeletion();
        $request->all = true;
        $request->iris = ['/ignored'];

        $container->get(OrphanedResourceDeletionStateProcessor::class)->process($request, new Post());
    }

    public function test_the_deletion_processor_refuses_anything_but_a_deletion_request(): void
    {
        $container = $this->loadContainer();
        $container->set('silverback.api_components.orphaned_resource.deleter', $this->createStub(OrphanedResourceDeleter::class));

        $this->expectException(\InvalidArgumentException::class);
        $container->get(OrphanedResourceDeletionStateProcessor::class)->process(null, new Post());
    }

    /**
     * @param list<string> $componentGroups
     */
    private function detectorReturning(array $componentGroups): OrphanedResourceDetector
    {
        $detector = $this->createStub(OrphanedResourceDetector::class);
        $detector->method('detect')->willReturn(new OrphanedResourceReport(new \DateTimeImmutable(), $componentGroups));

        return $detector;
    }

    private function loadContainer(bool $withTestDoubles = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_orphaned_resources.php');
        (new ResolveClassPass())->process($container);
        if ($withTestDoubles) {
            $container->set(self::STORE_ID, new InMemoryOrphanedResourceReportStore());
            $notifier = $this->createStub(OrphanedResourceNotifier::class);
            $notifier->method('notify')->willReturn(OrphanedResourceNotificationResult::NoRecipients);
            $container->set(self::NOTIFIER_ID, $notifier);
        }

        return $container;
    }
}
