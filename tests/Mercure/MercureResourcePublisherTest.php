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
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Mercure\MercureResourcePublisher;
use Silverback\ApiComponentsBundle\Message\PublishMercureUpdatesMessage;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Utility\PublicationDate;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MercureResourcePublisherTest extends TestCase
{
    /** @var string[] */
    private array $published = [];

    /** @var list<Update> */
    private array $updates = [];

    /** @var list<Request> */
    private array $anonymousRequests = [];

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    private function buildPublisher(
        callable $publish,
        ?MessageBusInterface $messageBus = null,
        bool $withLogger = true,
        ?ResourceMetadataCollection $resourceMetadata = null,
        ?\Closure $anonymousResponse = null,
        ?\Closure $writerData = null,
        string $resourceClass = Layout::class,
        array $changeSet = [],
    ): MercureResourcePublisher {
        $hub = new MockHub('https://internal.example.com/.well-known/mercure', new StaticTokenProvider('publisher-jwt'), $publish);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static fn (object $object, int $referenceType = UrlGeneratorInterface::ABS_PATH): string => (UrlGeneratorInterface::ABS_URL === $referenceType ? 'https://example.com' : '') . '/_/layouts/' . ($object->reference ?? 'first') . ($object instanceof DummyPublishableComponent && !PublicationDate::isActive($object->getPublishedAt()) ? '?draft=1' : '')
        );

        $resourceClassResolver = $this->createStub(ResourceClassResolverInterface::class);
        $resourceClassResolver->method('isResourceClass')->willReturn(true);
        $resourceClassResolver->method('getResourceClass')->willReturn($resourceClass);

        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn($resourceMetadata ?? new ResourceMetadataCollection($resourceClass, [
            new ApiResource(operations: [new Get(mercure: true)]),
        ]));

        $httpKernel = $this->createStub(HttpKernelInterface::class);
        $httpKernel->method('handle')->willReturnCallback(function (Request $request) use ($anonymousResponse): Response {
            $this->anonymousRequests[] = $request;

            return ($anonymousResponse ?? static fn (Request $request): Response => new Response('{"@id":"' . $request->getPathInfo() . '","public":true}'))($request);
        });

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $object, string $field): ?object => 'publishedResource' === $field ? $object->getPublishedResource() : $object->getPublishedAt());
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturnOnConsecutiveCalls($changeSet, [], [], []);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback($writerData ?? static fn (object $object): string => '{"@id":"/_/layouts/' . ($object->reference ?? 'first') . '","admin":true}');

        $publisher = new MercureResourcePublisher(
            new HubRegistry($hub, ['default' => $hub]),
            $iriConverter,
            $this->createStub(SerializerContextBuilderInterface::class),
            new RequestStack(),
            ['jsonld' => ['application/ld+json']],
            new AnonymousResourceReader($httpKernel, new RequestStack(), new TokenStorage(), []),
            new PublishableStatusChecker($registry, new PublishableAttributeReader($registry), $this->createStub(AuthorizationCheckerInterface::class), "is_granted('ROLE_ADMIN')"),
            $registry,
            $metadataFactory,
            $resourceClassResolver,
            $messageBus,
            null,
            null,
            null,
            null,
            $withLogger ? $this->logger : null,
        );
        $publisher->setSerializer($serializer);

        return $publisher;
    }

    private function recordingHub(): callable
    {
        return function (Update $update): string {
            $this->updates[] = $update;

            return 'urn:uuid:published';
        };
    }

    /**
     * @return list<array{string, string, bool}>
     */
    private function publishedShape(): array
    {
        return array_map(static fn (Update $update): array => [implode(', ', $update->getTopics()), (string) $update->getData(), $update->isPrivate()], $this->updates);
    }

    private function publishable(string $reference, ?string $publishedAt): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = $reference;
        $component->setPublishedAt(null === $publishedAt ? null : new \DateTime($publishedAt));

        return $component;
    }

    /**
     * @param list<object> $dispatched
     */
    private function recordingBus(array &$dispatched): MessageBusInterface
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        return $messageBus;
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

    public function test_the_public_update_is_the_anonymous_read_and_the_writer_view_follows_privately_on_the_admin_topic(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub());
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([
            ['https://example.com/_/layouts/first', '{"@id":"/_/layouts/first","public":true}', false],
            ['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true],
        ], $this->publishedShape());
    }

    public function test_the_anonymous_read_asks_for_the_resource_path_in_the_first_format(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub());
        $publisher->add($this->layout('first'), 'created');

        $publisher->propagate();

        self::assertCount(1, $this->anonymousRequests);
        self::assertSame('/_/layouts/first', $this->anonymousRequests[0]->getPathInfo());
        self::assertSame('application/ld+json', $this->anonymousRequests[0]->headers->get('Accept'));
    }

    public function test_a_resource_an_anonymous_caller_cannot_read_and_the_write_did_not_hide_publishes_only_the_writer_view(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), anonymousResponse: $this->denied());
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true]], $this->publishedShape());
    }

    /**
     * @return iterable<string, array{class-string, \Closure(): object, array<string, array{mixed, mixed}>}>
     */
    public static function writesThatHide(): iterable
    {
        yield 'a route going live in the future' => [Route::class, static fn (): Route => new Route(), ['liveAt' => [new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+1 day')]]];
        yield 'a route made a draft' => [Route::class, static fn (): Route => new Route(), ['liveAt' => [new \DateTimeImmutable('-1 day'), null]]];
        yield 'a page losing its route' => [Page::class, static fn (): Page => new Page(), ['route' => [new Route(), null]]];
        yield 'a page moved under another page' => [Page::class, static fn (): Page => new Page(), ['parentPage' => [null, new Page()]]];
        yield 'page data moved under other page data' => [PageData::class, static fn (): PageData => new PageData(), ['parentPageData' => [new PageData(), null]]];
    }

    /**
     * @param class-string                       $class
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    #[DataProvider('writesThatHide')]
    public function test_a_write_that_hid_a_resource_publishes_a_public_delete_before_the_writer_view(string $class, \Closure $resource, array $changeSet): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), anonymousResponse: $this->denied(), resourceClass: $class, changeSet: $changeSet);
        $publisher->add($resource(), 'updated');

        $publisher->propagate();

        self::assertSame([
            ['https://example.com/_/layouts/first', '{"@id":"\\/_\\/layouts\\/first"}', false],
            ['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true],
        ], $this->publishedShape());
    }

    /**
     * @return iterable<string, array{class-string, \Closure(): object, array<string, array{mixed, mixed}>}>
     */
    public static function writesThatDidNotHide(): iterable
    {
        yield 'a scheduled route rescheduled' => [Route::class, static fn (): Route => new Route(), ['liveAt' => [new \DateTimeImmutable('+1 day'), new \DateTimeImmutable('+2 days')]]];
        yield 'a draft route scheduled' => [Route::class, static fn (): Route => new Route(), ['liveAt' => [null, new \DateTimeImmutable('+1 day')]]];
        yield 'a live route moved to an earlier date' => [Route::class, static fn (): Route => new Route(), ['liveAt' => [new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('-2 days')]]];
        yield 'a route renamed' => [Route::class, static fn (): Route => new Route(), ['path' => ['/a', '/b']]];
        yield 'a page given a route' => [Page::class, static fn (): Page => new Page(), ['route' => [null, new Route()]]];
        yield 'a page given another route' => [Page::class, static fn (): Page => new Page(), ['route' => [new Route(), new Route()]]];
        yield 'a page retitled' => [Page::class, static fn (): Page => new Page(), ['title' => ['a', 'b']]];
        yield 'a layout moved under a page field it does not have' => [Layout::class, static fn (): Layout => new Layout(), ['parentPage' => [null, new Page()]]];
    }

    /**
     * @param class-string                       $class
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    #[DataProvider('writesThatDidNotHide')]
    public function test_a_write_that_did_not_hide_a_resource_publishes_nothing_publicly_when_it_cannot_be_read(string $class, \Closure $resource, array $changeSet): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), anonymousResponse: $this->denied(), resourceClass: $class, changeSet: $changeSet);
        $publisher->add($resource(), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true]], $this->publishedShape());
    }

    public function test_a_resource_still_readable_after_a_write_that_could_hide_it_publishes_its_public_payload(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: Page::class, changeSet: ['parentPage' => [null, new Page()]]);
        $publisher->add(new Page(), 'updated');

        $publisher->propagate();

        self::assertSame([
            ['https://example.com/_/layouts/first', '{"@id":"/_/layouts/first","public":true}', false],
            ['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true],
        ], $this->publishedShape());
    }

    public function test_a_resource_hidden_by_a_write_that_cannot_be_serialized_for_the_writer_publishes_only_the_delete(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), anonymousResponse: $this->denied(), writerData: static fn (): never => throw new InvalidArgumentException('No IRI'), resourceClass: Route::class, changeSet: ['liveAt' => [new \DateTimeImmutable('-1 day'), null]]);
        $publisher->add(new Route(), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first', '{"@id":"\\/_\\/layouts\\/first"}', false]], $this->publishedShape());
    }

    private function denied(): \Closure
    {
        return static fn (): never => throw new NotFoundHttpException();
    }

    public function test_the_writer_view_is_not_published_when_it_is_the_public_payload(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), writerData: static fn (object $object): string => '{"@id":"/_/layouts/' . $object->reference . '","public":true}');
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first', '{"@id":"/_/layouts/first","public":true}', false]], $this->publishedShape());
    }

    public function test_the_writer_view_is_not_published_when_only_the_identifiers_made_per_serialization_differ(): void
    {
        $publisher = $this->buildPublisher(
            $this->recordingHub(),
            anonymousResponse: static fn (): Response => new Response('{"@id":"/_/layouts/first","_metadata":{"@id":"\/.well-known\/genid\/aaa","persisted":true},"x":{"@id":"/.well-known/genid/ccc"},"m":[{"@id":"\/_api\/_\/media_objects\/0a1b","y":1},{"@id":"/_/media_objects/2c3d"}]}'),
            writerData: static fn (): string => '{"@id":"/_/layouts/first","_metadata":{"@id":"\/.well-known\/genid\/bbb","persisted":true},"x":{"@id":"/.well-known/genid/ddd"},"m":[{"@id":"\/_api\/_\/media_objects\/4e5f","y":1},{"@id":"/_/media_objects/6a7b"}]}',
        );
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertCount(1, $this->updates);
        self::assertFalse($this->updates[0]->isPrivate());
    }

    public function test_a_writer_view_with_different_content_is_published_even_with_the_same_blank_node_ids(): void
    {
        $publisher = $this->buildPublisher(
            $this->recordingHub(),
            anonymousResponse: static fn (): Response => new Response('{"@id":"/_/layouts/first","_metadata":{"@id":"\/.well-known\/genid\/aaa"}}'),
            writerData: static fn (): string => '{"@id":"/_/layouts/first","_metadata":{"@id":"\/.well-known\/genid\/aaa","admin":true}}',
        );
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertCount(2, $this->updates);
    }

    public function test_a_writer_view_that_cannot_be_serialized_leaves_only_the_public_update(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), writerData: static fn (): never => throw new InvalidArgumentException('No IRI'));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first', '{"@id":"/_/layouts/first","public":true}', false]], $this->publishedShape());
    }

    public function test_a_removed_resource_is_one_public_delete_without_an_anonymous_read(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub());
        $publisher->add($this->layout('first'), 'deleted');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first', '{"@id":"\\/_\\/layouts\\/first"}', false]], $this->publishedShape());
        self::assertSame([], $this->anonymousRequests);
    }

    public function test_static_data_is_published_once_as_configured(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceMetadata: new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: ['data' => '{"static":true}', 'private' => true])]),
        ]));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first', '{"static":true}', true]], $this->publishedShape());
        self::assertSame([], $this->anonymousRequests);
    }

    public function test_configured_topics_get_the_admin_query_for_the_writer_view(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceMetadata: new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: ['topics' => ['https://example.com/a', 'https://example.com/b?draft=1']])]),
        ]));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([
            ['https://example.com/a, https://example.com/b?draft=1', '{"@id":"/_/layouts/first","public":true}', false],
            ['https://example.com/a?draft=1, https://example.com/b?draft=1', '{"@id":"/_/layouts/first","admin":true}', true],
        ], $this->publishedShape());
    }

    public function test_a_resource_configured_as_private_publishes_its_public_update_privately(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceMetadata: new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: ['private' => true])]),
        ]));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([true, true], array_map(static fn (Update $update): bool => $update->isPrivate(), $this->updates));
    }

    public function test_a_draft_is_published_only_privately_on_its_draft_topic(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class);
        $publisher->add($this->publishable('first', null), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true]], $this->publishedShape());
        self::assertSame([], $this->anonymousRequests);
    }

    public function test_a_draft_that_was_never_published_publishes_no_delete_when_its_date_changes(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class, changeSet: ['publishedAt' => [null, new \DateTime('2999-01-01')]]);
        $publisher->add($this->publishable('first', '2999-01-01'), 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true]], $this->publishedShape());
    }

    public function test_a_draft_whose_writer_view_cannot_be_serialized_publishes_nothing(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), writerData: static fn (): never => throw new InvalidArgumentException('No IRI'), resourceClass: DummyPublishableComponent::class);
        $publisher->add($this->publishable('first', null), 'updated');

        $publisher->propagate();

        self::assertSame([], $this->updates);
    }

    public function test_a_published_resource_moved_to_a_future_date_is_deleted_from_its_public_topic_before_the_private_update(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class, changeSet: ['publishedAt' => [new \DateTime('-1 day'), new \DateTime('2999-01-01')]]);
        $component = $this->publishable('first', '2999-01-01');
        $publisher->add($component, 'updated');

        $publisher->propagate();

        self::assertSame([
            ['https://example.com/_/layouts/first', '{"@id":"\\/_\\/layouts\\/first"}', false],
            ['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true],
        ], $this->publishedShape());
    }

    public function test_a_resource_added_again_after_its_change_set_is_cleared_is_still_deleted_from_its_public_topic(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class, changeSet: ['publishedAt' => [new \DateTime('-1 day'), new \DateTime('2999-01-01')]]);
        $component = $this->publishable('first', '2999-01-01');
        $publisher->add($component, 'updated');
        $publisher->add($component, 'updated');

        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/first', 'https://example.com/_/layouts/first?draft=1'], array_map(static fn (Update $update): string => $update->getTopics()[0], $this->updates));
    }

    public function test_a_published_resource_is_read_anonymously(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class);
        $publisher->add($this->publishable('first', '-1 day'), 'updated');

        $publisher->propagate();

        self::assertCount(1, $this->anonymousRequests);
        self::assertSame(['https://example.com/_/layouts/first', 'https://example.com/_/layouts/first?draft=1'], array_map(static fn (Update $update): string => $update->getTopics()[0], $this->updates));
    }

    public function test_a_draft_whose_publication_date_has_passed_is_still_a_draft_until_it_is_merged(): void
    {
        $publisher = $this->buildPublisher($this->recordingHub(), resourceClass: DummyPublishableComponent::class);
        $draft = $this->publishable('first', '-1 day');
        $draft->setPublishedResource($this->publishable('published', '-2 days'));
        $publisher->add($draft, 'updated');

        $publisher->propagate();

        self::assertSame([['https://example.com/_/layouts/first?draft=1', '{"@id":"/_/layouts/first","admin":true}', true]], $this->publishedShape());
        self::assertSame([], $this->anonymousRequests);
    }

    public function test_an_asynchronous_publish_dispatches_the_public_and_private_updates_in_order_as_one_message(): void
    {
        $dispatched = [];
        $publisher = $this->buildPublisher($this->recordingHub(), $this->recordingBus($dispatched));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(PublishMercureUpdatesMessage::class, $dispatched[0]);
        self::assertNull($dispatched[0]->hub);
        self::assertSame([[false, '{"@id":"/_/layouts/first","public":true}'], [true, '{"@id":"/_/layouts/first","admin":true}']], array_map(static fn (Update $update): array => [$update->isPrivate(), $update->getData()], $dispatched[0]->updates));
        self::assertSame([], $this->updates);
    }

    public function test_an_asynchronous_publish_names_the_configured_hub(): void
    {
        $dispatched = [];
        $publisher = $this->buildPublisher($this->recordingHub(), $this->recordingBus($dispatched), resourceMetadata: new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: ['hub' => 'default'])]),
        ]));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame('default', $dispatched[0]->hub);
    }

    public function test_an_asynchronous_publish_of_one_update_dispatches_the_update_itself(): void
    {
        $dispatched = [];
        $publisher = $this->buildPublisher($this->recordingHub(), $this->recordingBus($dispatched));
        $publisher->add($this->layout('first'), 'deleted');

        $publisher->propagate();

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(Update::class, $dispatched[0]);
    }

    public function test_a_publish_with_async_updates_disabled_goes_straight_to_the_hub(): void
    {
        $dispatched = [];
        $publisher = $this->buildPublisher($this->recordingHub(), $this->recordingBus($dispatched), resourceMetadata: new ResourceMetadataCollection(Layout::class, [
            new ApiResource(operations: [new Get(mercure: ['enable_async_update' => false])]),
        ]));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame([], $dispatched);
        self::assertCount(2, $this->updates);
    }

    public function test_a_failed_asynchronous_pair_is_logged_with_both_topics(): void
    {
        $exception = $this->hubFailure();
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(static function (object $message) use ($exception): Envelope {
            throw new HandlerFailedException(new Envelope($message), [$exception]);
        });
        $publisher = $this->buildPublisher($this->recordingHub(), $messageBus);
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertCount(1, $this->logger->records);
        self::assertSame(['https://example.com/_/layouts/first', 'https://example.com/_/layouts/first?draft=1'], $this->logger->records[0][2]['topics']);
    }

    public function test_an_http_client_failure_of_an_asynchronous_pair_is_logged(): void
    {
        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willThrowException(new TransportException('Connection refused'));
        $publisher = $this->buildPublisher($this->recordingHub(), $messageBus);
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertCount(1, $this->logger->records);
    }

    public function test_a_failed_public_update_does_not_stop_the_private_one_when_published_directly(): void
    {
        $publisher = $this->buildPublisher($this->failingFor('https://example.com/_/layouts/first', $this->hubFailure()));
        $publisher->add($this->layout('first'), 'updated');

        $publisher->propagate();

        self::assertSame(['https://example.com/_/layouts/first?draft=1'], $this->published);
        self::assertSame(['https://example.com/_/layouts/first'], $this->logger->records[0][2]['topics']);
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
