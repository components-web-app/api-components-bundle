<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Realtime;

use ApiPlatform\Test\Client;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RealtimeEventStateProcessor;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CookieTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\Mercure\Update;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RealtimeEventTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CookieTrait;
    use LogRecordsTrait;
    use MercureTrait;
    use ResourceTrait;

    private const string ENDPOINT = '/_/realtime';
    private const string BEACON_CONTENT_TYPE = 'text/plain;charset=UTF-8';
    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';
    private const string OTHER_CLIENT_ID = '1b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';

    public function test_an_anonymous_request_is_refused_by_the_firewall_and_publishes_nothing(): void
    {
        $client = static::createClient();

        self::send($client, self::body());

        self::assertResponseStatusCodeSame(401);
        self::assertNoMercureUpdatePublished();
    }

    public function test_a_user_without_the_administrator_role_is_refused_by_the_operations_own_security(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::send($client, self::body());

        self::assertResponseStatusCodeSame(403);
        self::assertNoMercureUpdatePublished();
    }

    public function test_the_operation_cannot_be_reached_with_get(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('GET', self::ENDPOINT, ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonContentTypes(): iterable
    {
        yield 'application/json' => ['application/json'];
        yield 'application/ld+json' => ['application/ld+json'];
    }

    #[DataProvider('jsonContentTypes')]
    public function test_an_admins_json_event_is_accepted_with_an_empty_response(string $contentType): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::send($client, self::body(), $contentType);

        self::assertResponseStatusCodeSame(202);
        self::assertSame('', $response->getContent());
        self::assertCount(1, self::mercureUpdates());
    }

    public function test_a_presence_event_leaving_sent_by_send_beacon_as_text_is_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::send($client, self::leftBody(), self::BEACON_CONTENT_TYPE);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['page' => '/_/pages/1', 'state' => 'left'], self::mercureUpdateData(self::onlyUpdate())['data']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupportedBodies(): iterable
    {
        yield 'any other event as text' => [self::BEACON_CONTENT_TYPE, self::body()];
        yield 'a presence event that is not leaving as text' => [self::BEACON_CONTENT_TYPE, '{"clientId": "0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b", "event": "presence", "data": {"state": "editing"}}'];
        yield 'a presence event leaving as a form' => ['application/x-www-form-urlencoded', self::leftBody()];
    }

    #[DataProvider('unsupportedBodies')]
    public function test_any_other_body_is_a_415_and_publishes_nothing(string $contentType, string $body): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::send($client, $body, $contentType);

        self::assertResponseStatusCodeSame(415);
        self::assertNoMercureUpdatePublished();
    }

    public function test_the_event_is_published_once_privately_on_the_site_wide_topic_with_no_sse_type(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        $clientId = Uuid::uuid4()->toString();

        self::send($client, json_encode([
            'clientId' => $clientId,
            'event' => 'presence',
            'iri' => '/_/pages/1',
            'data' => ['page' => '/_/pages/1', 'state' => 'editing', 'field' => 'title', 'empty' => new \stdClass()],
            'user' => ['id' => '/users/forged', 'name' => 'forged'],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);
        $update = self::onlyUpdate();
        self::assertSame(['http://example.com/_/realtime'], $update->getTopics());
        self::assertTrue($update->isPrivate());
        self::assertNull($update->getType());
        self::assertNull($update->getId());
        $data = self::mercureUpdateData($update);
        self::assertSame(['@type', 'v', 'clientId', 'user', 'event', 'iri', 'data', 'sentAt', 'expiresAt'], array_keys($data));
        self::assertSame('CwaRealtimeEvent', $data['@type']);
        self::assertSame(1, $data['v']);
        self::assertSame($clientId, $data['clientId']);
        self::assertSame(['id' => self::iriOf($admin), 'name' => 'new_user'], $data['user']);
        self::assertSame('presence', $data['event']);
        self::assertSame('/_/pages/1', $data['iri']);
        self::assertStringContainsString('"data":{"page":"\/_\/pages\/1","state":"editing","field":"title","empty":{}}', $update->getData());
        self::assertStringNotContainsString('user@example.com', $update->getData());
        $sentAt = new \DateTimeImmutable($data['sentAt']);
        self::assertSame(45, new \DateTimeImmutable($data['expiresAt'])->getTimestamp() - $sentAt->getTimestamp());
        self::assertEqualsWithDelta(time(), $sentAt->getTimestamp(), 5);
    }

    public function test_the_topic_includes_the_prefix_the_application_imports_api_platform_routes_under(): void
    {
        static::bootKernel(['routePrefix' => '/_api']);
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_api' . self::ENDPOINT, ['headers' => ['content-type' => 'application/json'], 'body' => self::body()]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(['http://example.com/_api/_/realtime'], self::onlyUpdate()->getTopics());
    }

    public function test_a_body_of_sixteen_kilobytes_is_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::send($client, self::bodyOfLength(16384));

        self::assertResponseStatusCodeSame(202);
    }

    public function test_a_body_over_sixteen_kilobytes_is_a_413_and_publishes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::send($client, self::bodyOfLength(16385));

        self::assertResponseStatusCodeSame(413);
        self::assertNoMercureUpdatePublished();
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function invalidBodies(): iterable
    {
        $clientId = '"clientId": "0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b"';

        yield 'an empty body' => ['', ['']];
        yield 'malformed json' => ['{"event": ', ['']];
        yield 'a list' => ['[]', ['']];
        yield 'no client id' => ['{"event": "presence"}', ['clientId']];
        yield 'a client id that is not a uuid' => ['{"clientId": "tab-1", "event": "presence"}', ['clientId']];
        yield 'no event' => ['{' . $clientId . '}', ['event']];
        yield 'an event that is not a short lowercase token' => ['{' . $clientId . ', "event": "Presence Update"}', ['event']];
        yield 'an iri that is not a string' => ['{' . $clientId . ', "event": "presence", "iri": 1}', ['iri']];
        yield 'data with a number too large to represent' => ['{' . $clientId . ', "event": "presence", "data": {"x": 1e999}}', ['data']];
        yield 'every field' => ['{"clientId": 1, "event": 1, "iri": []}', ['clientId', 'event', 'iri']];
    }

    /**
     * @param list<string> $propertyPaths
     */
    #[DataProvider('invalidBodies')]
    public function test_an_invalid_body_is_a_422_with_a_violation_on_each_field_and_publishes_nothing(string $body, array $propertyPaths): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::send($client, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($propertyPaths, array_column($response->toArray(false)['violations'], 'propertyPath'));
        self::assertNoMercureUpdatePublished();
    }

    public function test_a_client_over_its_rate_limit_is_a_429_with_the_seconds_until_its_next_event_and_publishes_nothing(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        self::drainClientRateLimit(self::iriOf($admin), self::CLIENT_ID, 6);

        self::send($client, self::body(self::CLIENT_ID));

        self::assertResponseStatusCodeSame(429);
        self::assertEqualsWithDelta(10, (int) self::getClient()->getResponse()->headers->get('retry-after'), 2);
        self::assertNoMercureUpdatePublished();
    }

    public function test_another_client_of_the_same_admin_is_not_limited_by_the_first_clients_events(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        self::drainClientRateLimit(self::iriOf($admin), self::CLIENT_ID, 2);

        self::send($client, self::body(self::OTHER_CLIENT_ID));

        self::assertResponseStatusCodeSame(202);
    }

    public function test_a_user_over_the_limit_across_their_clients_is_a_429_whatever_the_client_and_publishes_nothing(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        self::drainUserRateLimit(self::iriOf($admin), 6);

        foreach ([self::CLIENT_ID, Uuid::uuid4()->toString()] as $clientId) {
            self::send($client, self::body($clientId));

            self::assertResponseStatusCodeSame(429);
            self::assertEqualsWithDelta(10, (int) self::getClient()->getResponse()->headers->get('retry-after'), 2);
        }
        self::assertNoMercureUpdatePublished();
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function refusedByBoth(): iterable
    {
        yield 'the client limit waiting longer' => [6, 3, 10];
        yield 'the user limit waiting longer' => [2, 6, 10];
    }

    #[DataProvider('refusedByBoth')]
    public function test_an_event_both_limits_refuse_is_told_the_longer_wait(int $clientBuckets, int $userBuckets, int $retryAfter): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        self::drainClientRateLimit(self::iriOf($admin), self::CLIENT_ID, $clientBuckets);
        self::drainUserRateLimit(self::iriOf($admin), $userBuckets);

        self::send($client, self::body(self::CLIENT_ID));

        self::assertResponseStatusCodeSame(429);
        self::assertEqualsWithDelta($retryAfter, (int) self::getClient()->getResponse()->headers->get('retry-after'), 2);
    }

    public function test_another_admin_is_not_limited_by_the_first_admins_events(): void
    {
        $client = static::createClient();
        $admin = self::createUser(['ROLE_ADMIN'], 'first_admin', 'first@example.com');
        self::drainClientRateLimit(self::iriOf($admin), self::CLIENT_ID, 2);
        self::drainUserRateLimit(self::iriOf($admin), 2);
        self::loginAsAdmin($client);

        self::send($client, self::body(self::CLIENT_ID));

        self::assertResponseStatusCodeSame(202);
    }

    public function test_a_cross_site_text_presence_event_leaving_is_refused_and_publishes_and_counts_nothing(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        HubStub::clearPublished();

        $client->request('POST', self::ENDPOINT, ['headers' => ['accept' => 'application/ld+json', 'content-type' => self::BEACON_CONTENT_TYPE, 'origin' => 'https://attacker.example', 'sec-fetch-site' => 'cross-site'], 'body' => self::leftBody()]);

        self::assertResponseStatusCodeSame(403);
        self::assertNoMercureUpdatePublished();
        self::assertSame(20, self::remainingTokens('silverback.api_components.realtime.client_rate_limiter', RealtimeEventStateProcessor::clientRateLimiterKey(self::iriOf($admin), self::CLIENT_ID)));
        self::assertSame(60, self::remainingTokens('silverback.api_components.realtime.user_rate_limiter', self::iriOf($admin)));
    }

    public function test_a_cross_site_json_event_is_accepted_and_published(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        HubStub::clearPublished();

        $client->request('POST', self::ENDPOINT, ['headers' => ['accept' => 'application/ld+json', 'content-type' => 'application/json', 'origin' => 'https://admin.example', 'sec-fetch-site' => 'cross-site'], 'body' => self::body()]);

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, self::mercureUpdates());
    }

    public function test_a_same_site_text_presence_event_leaving_is_accepted_and_counted(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        HubStub::clearPublished();

        $client->request('POST', self::ENDPOINT, ['headers' => ['accept' => 'application/ld+json', 'content-type' => self::BEACON_CONTENT_TYPE, 'origin' => 'http://app.example.com', 'sec-fetch-site' => 'same-site'], 'body' => self::leftBody()]);

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, self::mercureUpdates());
        self::assertSame(19, self::remainingTokens('silverback.api_components.realtime.client_rate_limiter', RealtimeEventStateProcessor::clientRateLimiterKey(self::iriOf($admin), self::CLIENT_ID)));
    }

    public function test_a_publish_the_hub_refuses_is_logged_and_still_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        HubStub::setUnreachable(true);

        $response = self::send($client, self::body());

        self::assertResponseStatusCodeSame(202);
        self::assertSame('', $response->getContent());
        self::assertLogged('the failed real-time publish', static fn (LogRecord $record): bool => Level::Error === $record->level && str_contains($record->message, 'The real-time event "presence" could not be published on "http://example.com/_/realtime"'));
        self::assertSame([], HubStub::getPublished());
    }

    public function test_a_publish_that_times_out_is_logged_and_still_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        HubStub::failWith(new TimeoutException('Idle timeout reached for "https://example.com/.well-known/mercure".'));

        self::send($client, self::body());

        self::assertResponseStatusCodeSame(202);
        self::assertLogged('the timed out real-time publish', static fn (LogRecord $record): bool => Level::Error === $record->level && str_contains($record->message, 'Idle timeout reached'));
    }

    public function test_an_admins_mercure_cookie_grants_the_realtime_topic_with_no_query(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_ADMIN'], 'admin', 'admin@example.com', 'admin-password');

        self::requestJsonLd($client, 'POST', '/login', ['username' => 'admin', 'password' => 'admin-password']);

        self::assertResponseStatusCodeSame(204);
        self::assertContains('http://example.com/_/realtime\?', self::mercureCookieTopics());
    }

    public function test_a_users_mercure_cookie_grants_nothing_that_matches_the_realtime_topic(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_USER'], 'user', 'user@example.com', 'user-password');

        self::requestJsonLd($client, 'POST', '/login', ['username' => 'user', 'password' => 'user-password']);

        self::assertResponseStatusCodeSame(204);
        $topics = self::mercureCookieTopics();
        self::assertNotEmpty($topics);
        foreach ($topics as $topic) {
            self::assertDoesNotMatchRegularExpression('#/_/(realtime|:\w+)\\\\\?#', $topic);
        }
    }

    private static function send(Client $client, string $body, ?string $contentType = 'application/json'): ResponseInterface
    {
        HubStub::clearPublished();
        $headers = ['accept' => 'application/ld+json'];
        if (null !== $contentType) {
            $headers['content-type'] = $contentType;
        }

        return $client->request('POST', self::ENDPOINT, ['headers' => $headers, 'body' => $body]);
    }

    private static function drainClientRateLimit(string $userIri, string $clientId, int $bursts): void
    {
        self::drainRateLimit('silverback.api_components.realtime.client_rate_limiter', RealtimeEventStateProcessor::clientRateLimiterKey($userIri, $clientId), 20, $bursts);
    }

    private static function drainUserRateLimit(string $userIri, int $bursts): void
    {
        self::drainRateLimit('silverback.api_components.realtime.user_rate_limiter', $userIri, 60, $bursts);
    }

    private static function remainingTokens(string $limiterId, string $key): int
    {
        $limiter = static::getContainer()->get($limiterId);
        \assert($limiter instanceof RateLimiterFactory);

        return $limiter->create($key)->consume(0)->getRemainingTokens();
    }

    private static function drainRateLimit(string $limiterId, string $key, int $burst, int $bursts): void
    {
        $limiter = static::getContainer()->get($limiterId);
        \assert($limiter instanceof RateLimiterFactory);
        $bucket = $limiter->create($key);
        for ($i = 0; $i < $bursts; ++$i) {
            $bucket->reserve($burst);
        }
        static::rebootKernel();
    }

    private static function body(?string $clientId = null): string
    {
        return json_encode(['clientId' => $clientId ?? Uuid::uuid4()->toString(), 'event' => 'presence'], \JSON_THROW_ON_ERROR);
    }

    private static function leftBody(): string
    {
        return json_encode(['clientId' => self::CLIENT_ID, 'event' => 'presence', 'data' => ['page' => '/_/pages/1', 'state' => 'left']], \JSON_THROW_ON_ERROR);
    }

    private static function bodyOfLength(int $length): string
    {
        $prefix = '{"clientId": "' . Uuid::uuid4()->toString() . '", "event": "presence", "data": "';
        $suffix = '"}';

        return $prefix . str_repeat('x', $length - \strlen($prefix) - \strlen($suffix)) . $suffix;
    }

    private static function onlyUpdate(): Update
    {
        $updates = self::mercureUpdates();
        self::assertCount(1, $updates, self::describeMercureUpdates());

        return $updates[0];
    }
}
