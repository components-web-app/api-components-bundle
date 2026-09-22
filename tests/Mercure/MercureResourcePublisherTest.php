<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Mercure;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

class MercureResourcePublisherTest extends TestCase
{
    /** @var string[] */
    private array $published = [];

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    private function buildPublisher(callable $publish, ?MessageBusInterface $messageBus = null, bool $withLogger = true, ?ResourceMetadataCollection $resourceMetadata = null): MercureResourcePublisher
    {
        $hub = new MockHub('https://internal.example.com/.well-known/mercure', new StaticTokenProvider('publisher-jwt'), $publish);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn (object $object, int $referenceType = UrlGeneratorInterface::ABS_PATH): string => (UrlGeneratorInterface::ABS_URL === $referenceType ? 'https://example.com' : '') . '/_/layouts/' . $object->reference
        );

        $resourceClassResolver = $this->createStub(ResourceClassResolverInterface::class);
        $resourceClassResolver->method('isResourceClass')->willReturn(true);
        $resourceClassResolver->method('getResourceClass')->willReturn(Layout::class);

        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn($resourceMetadata ?? new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: true)]),
        ]));

        return new MercureResourcePublisher(
            new HubRegistry($hub, ['default' => $hub]),
            $iriConverter,
            $this->createStub(SerializerContextBuilderInterface::class),
            new RequestStack(),
            ['jsonld' => ['application/ld+json']],
            $metadataFactory,
            $resourceClassResolver,
            $messageBus,
            null,
            null,
            null,
            null,
            $withLogger ? $this->logger : null,
        );
    }

    private function layout(string $reference): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;

        return $layout;
    }

    private function failingFor(string $failingTopic, \Throwable $exception): callable
    {
        return function (Update $update) use ($failingTopic, $exception): string {
            $topic = $update->getTopics()[0];
            if ($topic === $failingTopic) {
                throw $exception;
            }
            $this->published[] = $topic;

            return 'urn:uuid:published';
        };
    }

    private function hubFailure(): MercureRuntimeException
    {
        return new MercureRuntimeException('Failed to send an update.', 0, new TransportException('Could not resolve host: hub'));
    }

    public function test_the_service_definition_wires_the_logger_explicitly_and_optionally(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_mercure_publisher.php');

        $arguments = $container->getDefinition('silverback.api_components.mercure.resource_publisher')->getArguments();
        $logger = end($arguments);

        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
    }

    public function test_a_resource_with_no_operation_is_not_published(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('', $this->hubFailure()), resourceMetadata: new ResourceMetadataCollection(Layout::class, []));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([], $this->published);
    }

    public function test_a_hub_failure_does_not_throw_out_of_propagate(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', $this->hubFailure()));
        $publisher->add($this->layout('first'), 'deleted');

        $publisher->propagate();

        self::assertSame([], $this->published);
    }

    public function test_a_hub_failure_is_logged_at_error_level_with_the_topic_and_the_exception(): void
    {
        $exception = $this->hubFailure();
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', $exception));
        $publisher->add($this->layout('first'), 'deleted');

        $publisher->propagate();

        self::assertCount(1, $this->logger->records);
        [$level, $message, $context] = $this->logger->records[0];
        self::assertSame(LogLevel::ERROR, $level);
        self::assertStringContainsString('https://example.com/_/layouts/first', $message);
        self::assertStringContainsString('Failed to send an update.', $message);
        self::assertSame(['https://example.com/_/layouts/first'], $context['topics']);
        self::assertSame('https://example.com/_/layouts/first', $context['resource']);
        self::assertSame($exception, $context['exception']);
    }

    public function test_the_remaining_updates_are_still_published_after_one_fails(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/second', $this->hubFailure()));
        $publisher->add($this->layout('first'), 'deleted');
        $publisher->add($this->layout('second'), 'deleted');
        $publisher->add($this->layout('third'), 'deleted');

        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/first', 'https://example.com/_/layouts/third'], $this->published);
        self::assertCount(1, $this->logger->records);
    }

    public function test_an_http_client_exception_raised_by_a_hub_is_treated_as_a_hub_failure(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', new TransportException('Connection refused')));
        $publisher->add($this->layout('first'), 'deleted');
        $publisher->add($this->layout('second'), 'deleted');

        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/second'], $this->published);
        self::assertCount(1, $this->logger->records);
    }

    public function test_a_hub_failure_without_a_logger_does_not_throw(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', $this->hubFailure()), withLogger: false);
        $publisher->add($this->layout('first'), 'deleted');
        $publisher->add($this->layout('second'), 'deleted');

        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/second'], $this->published);
    }

    public function test_a_hub_failure_from_a_synchronously_handled_messenger_dispatch_is_logged(): void
    {
        $exception = $this->hubFailure();
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (object $message) use ($exception): Envelope {
            throw new HandlerFailedException(new Envelope($message), [$exception]);
        });
        $publisher = $this->buildPublisher(static fn (): string => 'urn:uuid:published', $messageBus);
        $publisher->add($this->layout('first'), 'deleted');
        $publisher->add($this->layout('second'), 'deleted');

        $publisher->propagate();

        self::assertCount(2, $this->logger->records);
        self::assertSame($exception, $this->logger->records[0][2]['exception']);
    }

    public function test_an_exception_that_is_not_a_hub_failure_is_not_swallowed(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', new \LogicException('A programming error')));
        $publisher->add($this->layout('first'), 'deleted');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A programming error');

        $publisher->propagate();
    }

    public function test_the_reentrancy_guard_is_lowered_after_a_hub_failure(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', $this->hubFailure()));
        $publisher->add($this->layout('first'), 'deleted');
        $publisher->propagate();

        $publisher->add($this->layout('second'), 'deleted');
        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/second'], $this->published);
    }

    public function test_the_reentrancy_guard_is_lowered_after_an_exception_that_is_not_swallowed(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', new \LogicException('A programming error')));
        $publisher->add($this->layout('first'), 'deleted');
        try {
            $publisher->propagate();
            self::fail('The exception was swallowed.');
        } catch (\LogicException) {
        }

        $publisher->add($this->layout('second'), 'deleted');
        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/second'], $this->published);
    }
}

class RecordingLogger extends AbstractLogger
{
    /** @var array<int, array{0: mixed, 1: string, 2: array}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
