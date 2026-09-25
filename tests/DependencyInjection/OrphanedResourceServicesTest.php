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
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedResourceReportStateProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Message\ScanOrphanedResourcesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class OrphanedResourceServicesTest extends TestCase
{
    private const string BUS_ID = 'messenger.default_bus';
    private const string DETECTOR_ID = 'silverback.api_components.orphaned_resource.detector';
    private const string HANDLER_ID = 'silverback.api_components.message_handler.scan_orphaned_resources';

    public function test_the_report_is_stored_in_the_application_cache_pool(): void
    {
        $container = $this->loadContainer();

        self::assertSame(
            'cache.app',
            (string) $container->getDefinition('silverback.api_components.orphaned_resource.report_store')->getArgument(0)
        );
        self::assertSame('silverback.api_components.orphaned_resource.report_store', (string) $container->getAlias(OrphanedResourceReportStore::class));
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

    /**
     * @param list<string> $componentGroups
     */
    private function detectorReturning(array $componentGroups): OrphanedResourceDetector
    {
        $detector = $this->createStub(OrphanedResourceDetector::class);
        $detector->method('detect')->willReturn(new OrphanedResourceReport(new \DateTimeImmutable(), $componentGroups));

        return $detector;
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_orphaned_resources.php');
        (new ResolveClassPass())->process($container);
        $container->set('cache.app', new ArrayAdapter());

        return $container;
    }
}
