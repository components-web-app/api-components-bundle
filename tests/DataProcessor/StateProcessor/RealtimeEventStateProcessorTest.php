<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RealtimeEventStateProcessor;
use Silverback\ApiComponentsBundle\Helper\Realtime\LastWriteResolver;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class RealtimeEventStateProcessorTest extends TestCase
{
    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';
    private const string TOPIC = 'https://example.com/_/realtime';

    /** @var array<string, list<Update>> */
    private array $published = ['default' => [], 'other' => []];

    private ?\Throwable $publishFailure = null;

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $logs = [];

    private TokenStorage $tokenStorage;

    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->tokenStorage->setToken(new UsernamePasswordToken((new User())->setUsername('editor')->setEmailAddress('editor@example.com'), 'main', ['ROLE_ADMIN']));
        $this->storage = new InMemoryStorage();
    }

    public function test_an_event_is_published_once_privately_on_the_realtime_topic_with_no_sse_type_and_nothing_is_returned(): void
    {
        self::assertNull($this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence", "iri": "/_/pages/1", "data": {"page": "/_/pages/1", "state": "editing", "nested": {}}, "user": {"id": "/users/forged", "name": "forged"}}')]));

        self::assertCount(1, $this->published['default']);
        $update = $this->published['default'][0];
        self::assertSame([self::TOPIC], $update->getTopics());
        self::assertTrue($update->isPrivate());
        self::assertNull($update->getType());
        self::assertNull($update->getId());
        self::assertNull($update->getRetry());
        self::assertSame(
            '{"@type":"CwaRealtimeEvent","clientId":"' . self::CLIENT_ID . '","user":{"id":"\/users\/1","name":"editor"},"event":"presence","iri":"\/_\/pages\/1","data":{"page":"\/_\/pages\/1","state":"editing","nested":{}},"sentAt":"2026-10-21T10:00:00.250+00:00","expiresAt":"2026-10-21T10:00:45.250+00:00"}',
            $update->getData(),
        );
    }

    public function test_an_event_with_no_iri_or_data_publishes_them_as_null(): void
    {
        $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "tiptap:selection"}')]);

        self::assertSame(
            ['@type' => 'CwaRealtimeEvent', 'clientId' => self::CLIENT_ID, 'user' => ['id' => '/users/1', 'name' => 'editor'], 'event' => 'tiptap:selection', 'iri' => null, 'data' => null, 'sentAt' => '2026-10-21T10:00:00.250+00:00', 'expiresAt' => '2026-10-21T10:00:45.250+00:00'],
            json_decode($this->published['default'][0]->getData(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function test_the_event_is_published_on_the_configured_hub(): void
    {
        $this->processor(hubName: 'other')->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);

        self::assertSame([], $this->published['default']);
        self::assertCount(1, $this->published['other']);
    }

    public function test_an_invalid_body_publishes_nothing(): void
    {
        try {
            $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"event": "presence"}')]);
            self::fail('The body should have been refused');
        } catch (ValidationException) {
        }

        self::assertSame([], $this->published['default']);
    }

    public function test_a_call_with_no_request_is_an_invalid_body(): void
    {
        $this->expectException(ValidationException::class);

        $this->processor()->process(null, new Post());
    }

    public function test_a_caller_with_no_user_is_refused_and_nothing_is_published(): void
    {
        $this->tokenStorage->setToken(null);

        try {
            $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The caller should have been refused');
        } catch (AccessDeniedHttpException) {
        }

        self::assertSame([], $this->published['default']);
    }

    public function test_a_burst_of_twenty_is_allowed_and_the_twenty_first_is_refused_with_a_retry_after(): void
    {
        $processor = $this->processor();
        for ($i = 0; $i < 20; ++$i) {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
        }

        try {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The 21st event should have been refused');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(429, $exception->getStatusCode());
            self::assertSame(['Retry-After' => 1], $exception->getHeaders());
        }

        self::assertCount(20, $this->published['default']);
    }

    public function test_a_new_client_id_does_not_reset_the_users_limit(): void
    {
        $processor = $this->processor();
        for ($i = 0; $i < 20; ++$i) {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . \sprintf('%08x', $i) . '-2c4d-4e5f-8a6b-7c8d9e0f1a2b", "event": "presence"}')]);
        }

        try {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "1b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b", "event": "presence"}')]);
            self::fail('A new client id should not have reset the limit');
        } catch (TooManyRequestsHttpException) {
        }

        self::assertCount(20, $this->published['default']);
    }

    public function test_another_user_is_not_limited_by_the_first_users_events(): void
    {
        $processor = $this->processor();
        for ($i = 0; $i < 20; ++$i) {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
        }

        $this->tokenStorage->setToken(new UsernamePasswordToken((new User())->setUsername('other'), 'main', ['ROLE_ADMIN']));
        $this->processor(userIri: '/users/2')->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);

        self::assertCount(21, $this->published['default']);
    }

    public function test_the_rate_limiter_is_keyed_by_the_users_iri(): void
    {
        $this->rateLimiterFactory()->create('/users/1')->reserve(20);

        $this->expectException(TooManyRequestsHttpException::class);

        $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
    }

    public function test_the_retry_after_is_the_seconds_until_the_limiter_admits_another_event(): void
    {
        $processor = $this->processor();
        $limiter = $this->rateLimiterFactory()->create('/users/1');
        for ($i = 0; $i < 3; ++$i) {
            $limiter->reserve(20);
        }

        try {
            $processor->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The event should have been refused');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertEqualsWithDelta(4, $exception->getHeaders()['Retry-After'], 1);
        }
    }

    public function test_the_retry_after_is_never_less_than_a_second(): void
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit(0, new \DateTimeImmutable('-1 second'), false, 20));
        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        try {
            $this->processor(rateLimiterFactory: $factory)->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The event should have been refused');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertSame(['Retry-After' => 1], $exception->getHeaders());
            self::assertSame(RealtimeEventStateProcessor::TOO_MANY_REQUESTS_MESSAGE, $exception->getMessage());
        }
    }

    public function test_the_retry_after_counts_the_seconds_to_the_limiters_next_token(): void
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit(0, new \DateTimeImmutable('@' . (time() + 30)), false, 20));
        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        try {
            $this->processor(rateLimiterFactory: $factory)->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The event should have been refused');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertEqualsWithDelta(30, $exception->getHeaders()['Retry-After'], 1);
        }
    }

    public function test_a_cross_site_request_is_refused_before_anything_is_read_or_counted(): void
    {
        $limiter = $this->createMock(RateLimiterFactoryInterface::class);
        $limiter->expects(self::never())->method('create');

        try {
            $this->processor(rateLimiterFactory: $limiter)->process(null, new Post(), [], ['request' => $this->request('{"event": "presence"}', 'cross-site')]);
            self::fail('The cross-site request should have been refused');
        } catch (AccessDeniedHttpException $exception) {
            self::assertSame(RealtimeEventStateProcessor::CROSS_SITE_MESSAGE, $exception->getMessage());
        }

        self::assertSame([], $this->published['default']);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function fetchSites(): iterable
    {
        yield 'same-origin' => ['same-origin'];
        yield 'same-site' => ['same-site'];
        yield 'none, a request the user started' => ['none'];
        yield 'no Sec-Fetch-Site, from a browser that does not send it' => [null];
    }

    #[DataProvider('fetchSites')]
    public function test_a_request_that_is_not_cross_site_is_published(?string $fetchSite): void
    {
        $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}', $fetchSite)]);

        self::assertCount(1, $this->published['default']);
    }

    public function test_a_publish_the_hub_refuses_is_logged_and_is_a_502(): void
    {
        $this->publishFailure = new MercureRuntimeException('Failed to send an update.');

        try {
            $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
            self::fail('The event should have failed');
        } catch (HttpException $exception) {
            self::assertSame(502, $exception->getStatusCode());
            self::assertSame(RealtimeEventStateProcessor::PUBLISH_FAILED_MESSAGE, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertCount(1, $this->logs);
        self::assertSame('error', $this->logs[0][0]);
        self::assertSame('The real-time event "presence" could not be published on "' . self::TOPIC . '": Failed to send an update.', $this->logs[0][1]);
        self::assertSame(['topics' => [self::TOPIC], 'event' => 'presence', 'clientId' => self::CLIENT_ID, 'exception' => $this->publishFailure], $this->logs[0][2]);
    }

    public function test_a_hub_that_cannot_be_reached_is_a_502(): void
    {
        $this->publishFailure = new TransportException('Could not resolve host');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(RealtimeEventStateProcessor::PUBLISH_FAILED_MESSAGE);

        $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
    }

    public function test_a_hub_failure_is_a_502_without_a_logger(): void
    {
        $this->publishFailure = new MercureRuntimeException('Failed to send an update.');

        $this->expectException(HttpException::class);

        $this->processor(logger: false)->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
    }

    public function test_a_hub_configuration_error_is_not_turned_into_a_502(): void
    {
        $this->publishFailure = new \InvalidArgumentException('Invalid JWT');

        $this->expectException(\InvalidArgumentException::class);

        $this->processor()->process(null, new Post(), [], ['request' => $this->request('{"clientId": "' . self::CLIENT_ID . '", "event": "presence"}')]);
    }

    public function test_events_live_for_forty_five_seconds(): void
    {
        self::assertSame(45, RealtimeEventStateProcessor::LIFETIME_SECONDS);
    }

    public function test_the_rate_limit_is_a_token_bucket_of_twenty_refilled_at_ten_a_second(): void
    {
        self::assertSame(['id' => 'cwa_realtime', 'policy' => 'token_bucket', 'limit' => 20, 'rate' => ['interval' => '1 second', 'amount' => 10]], RealtimeEventStateProcessor::RATE_LIMITER_CONFIG);
    }

    public function test_the_processor_keeps_its_class_name_as_its_service_id_and_is_tagged_as_a_state_processor(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition(RealtimeEventStateProcessor::class);

        self::assertTrue($definition->hasTag('api_platform.state_processor'));
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(RealtimeEventStateProcessor::class, (string) $container->getAlias('silverback.api_components.api_platform.state_processor.realtime_event'));
        self::assertSame(MercureAuthorization::class, (string) $definition->getArgument('$mercureAuthorization'));
        self::assertSame(HubRegistry::class, (string) $definition->getArgument('$hubRegistry'));
        self::assertSame('silverback.api_components.realtime.rate_limiter', (string) $definition->getArgument('$rateLimiterFactory'));
        self::assertSame('silverback.api_components.helper.realtime.user_resolver', (string) $definition->getArgument('$userResolver'));
        self::assertSame('clock', (string) $definition->getArgument('$clock'));
        self::assertNull($definition->getArgument('$hubName'));
        self::assertSame('logger', (string) $definition->getArgument('$logger'));
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $definition->getArgument('$logger')->getInvalidBehavior());
    }

    public function test_the_rate_limiter_stores_its_buckets_in_its_own_cache_pool_with_no_lock_and_leaves_the_pool_to_framework(): void
    {
        $container = $this->loadContainer();

        $factory = $container->getDefinition('silverback.api_components.realtime.rate_limiter');
        self::assertSame(RateLimiterFactory::class, $factory->getClass());
        self::assertSame([RealtimeEventStateProcessor::RATE_LIMITER_CONFIG, 'silverback.api_components.realtime.rate_limiter_storage', null], array_map(static fn (mixed $argument): mixed => $argument instanceof Reference ? (string) $argument : $argument, $factory->getArguments()));

        $storage = $container->getDefinition('silverback.api_components.realtime.rate_limiter_storage');
        self::assertSame(CacheStorage::class, $storage->getClass());
        self::assertSame('silverback.api_components.realtime.rate_limiter_cache', (string) $storage->getArgument(0));

        self::assertFalse($container->has('silverback.api_components.realtime.rate_limiter_cache'));
    }

    public function test_the_realtime_helpers_read_the_token_and_the_current_request(): void
    {
        $container = $this->loadContainer();

        $userResolver = $container->getDefinition('silverback.api_components.helper.realtime.user_resolver');
        self::assertSame(RealtimeUserResolver::class, $userResolver->getClass());
        self::assertSame(['security.token_storage', IriConverterInterface::class], array_map('strval', $userResolver->getArguments()));
        self::assertSame('silverback.api_components.helper.realtime.user_resolver', (string) $container->getAlias(RealtimeUserResolver::class));

        $lastWriteResolver = $container->getDefinition('silverback.api_components.helper.realtime.last_write_resolver');
        self::assertSame(LastWriteResolver::class, $lastWriteResolver->getClass());
        self::assertSame(['request_stack', 'silverback.api_components.helper.realtime.user_resolver'], array_map('strval', $lastWriteResolver->getArguments()));
        self::assertSame('silverback.api_components.helper.realtime.last_write_resolver', (string) $container->getAlias(LastWriteResolver::class));
    }

    public function test_the_persisted_normalizer_records_the_last_write(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config')))->load('services_normalizers.php');

        self::assertSame('silverback.api_components.helper.realtime.last_write_resolver', (string) $container->getDefinition('silverback.api_components.serializer.normalizer.persisted')->getArgument(3));
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config')))->load('services_realtime.php');

        return $container;
    }

    private function request(string $content, ?string $fetchSite = null): Request
    {
        $server = ['CONTENT_TYPE' => 'text/plain;charset=UTF-8'];
        if (null !== $fetchSite) {
            $server['HTTP_SEC_FETCH_SITE'] = $fetchSite;
        }

        return Request::create('/_/realtime', 'POST', server: $server, content: $content);
    }

    private function rateLimiterFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory(RealtimeEventStateProcessor::RATE_LIMITER_CONFIG, $this->storage);
    }

    private function processor(?string $hubName = null, string $userIri = '/users/1', bool $logger = true, ?RateLimiterFactoryInterface $rateLimiterFactory = null): RealtimeEventStateProcessor
    {
        $authorization = $this->createStub(MercureAuthorization::class);
        $authorization->method('getRealtimeTopic')->willReturn(self::TOPIC);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn($userIri);

        return new RealtimeEventStateProcessor(
            $authorization,
            new HubRegistry($this->hub('default'), ['default' => $this->hub('default'), 'other' => $this->hub('other')]),
            $rateLimiterFactory ?? $this->rateLimiterFactory(),
            new RealtimeUserResolver($this->tokenStorage, $iriConverter),
            new MockClock(new \DateTimeImmutable('2026-10-21T10:00:00.250+00:00')),
            $hubName,
            $logger ? $this->logger() : null,
        );
    }

    private function hub(string $name): MockHub
    {
        return new MockHub('https://example.com/.well-known/mercure', new StaticTokenProvider('jwt'), function (Update $update) use ($name): string {
            if (null !== $this->publishFailure) {
                throw $this->publishFailure;
            }
            $this->published[$name][] = $update;

            return 'id';
        });
    }

    private function logger(): AbstractLogger
    {
        return new class($this->logs) extends AbstractLogger {
            /**
             * @param list<array{string, string, array<string, mixed>}> $logs
             */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message, $context];
            }
        };
    }
}
