<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Health;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;

final class HealthTest extends FunctionalTestCase
{
    use LogRecordsTrait;

    private const array CACHE_TAG_HEADERS = ['xkey', 'surrogate-key', 'cache-tags'];

    public function test_an_anonymous_health_check_answers_ok_when_the_database_is_reachable(): void
    {
        $response = static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['status' => 'ok'], $response->toArray());
    }

    public function test_a_health_check_response_is_never_stored_by_any_cache(): void
    {
        static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseIsNeverStored();
    }

    public function test_a_health_check_response_carries_no_cache_tags(): void
    {
        static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseCarriesNoCacheTagHeader();
    }

    public function test_a_health_check_response_sets_no_cookie(): void
    {
        static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseNotHasHeader('set-cookie');
    }

    public function test_a_health_check_can_be_made_with_head(): void
    {
        static::createClient()->request('HEAD', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('no-store', self::cacheControl());
    }

    #[DataProvider('methodsOtherThanGetAndHead')]
    public function test_a_health_check_only_answers_get_and_head(string $method): void
    {
        static::createClient()->request($method, '/_/health');

        self::assertResponseStatusCodeSame(405);
    }

    public function test_a_health_check_answers_503_with_a_short_reason_when_the_database_is_unreachable(): void
    {
        $client = static::createClient();
        UnreachableDatabaseMiddleware::setUnreachable(true);

        $response = $client->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(503);
        self::assertStringContainsString('no-store', self::cacheControl());
        self::assertStringNotContainsString('public', self::cacheControl());
        self::assertSame(['status' => 'unavailable', 'reason' => 'database'], $response->toArray(false));
        self::assertStringNotContainsString('unreachable-database-test-double', $response->getContent(false));
    }

    public function test_an_unreachable_database_is_logged_with_its_cause(): void
    {
        $client = static::createClient();
        UnreachableDatabaseMiddleware::setUnreachable(true);

        $client->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(503);
        self::assertLogged('a warning caused by the unreachable database', static fn ($record): bool => 'WARNING' === $record->level->getName()
            && ($record->context['exception'] ?? null) instanceof \Throwable
            && $record->context['exception']->getPrevious() instanceof UnreachableDatabaseException);
    }

    #[Depends('test_an_unreachable_database_is_logged_with_its_cause')]
    public function test_the_database_is_reachable_again_in_the_next_test(): void
    {
        static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_anonymous_liveness_check_answers_ok(): void
    {
        $response = static::createClient()->request('GET', '/_/health/live');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['status' => 'ok'], $response->toArray());
    }

    public function test_a_liveness_check_answers_ok_while_the_database_is_unreachable_and_readiness_does_not(): void
    {
        $client = static::createClient();
        UnreachableDatabaseMiddleware::setUnreachable(true);

        $response = $client->request('GET', '/_/health/live');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'ok'], $response->toArray());

        $client->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(503);
    }

    public function test_a_liveness_check_is_never_stored_carries_no_cache_tags_and_sets_no_cookie(): void
    {
        static::createClient()->request('GET', '/_/health/live');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseIsNeverStored();
        self::assertResponseCarriesNoCacheTagHeader();
        self::assertResponseNotHasHeader('set-cookie');
    }

    public function test_a_liveness_check_can_be_made_with_head(): void
    {
        static::createClient()->request('HEAD', '/_/health/live');

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('no-store', self::cacheControl());
    }

    #[DataProvider('methodsOtherThanGetAndHead')]
    public function test_a_liveness_check_only_answers_get_and_head(string $method): void
    {
        static::createClient()->request($method, '/_/health/live');

        self::assertResponseStatusCodeSame(405);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methodsOtherThanGetAndHead(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            yield $method => [$method];
        }
    }

    private static function cacheControl(): string
    {
        return (string) self::getClient()->getResponse()->headers->get('cache-control');
    }

    private static function assertResponseIsNeverStored(): void
    {
        self::assertStringContainsString('no-store', self::cacheControl());
        self::assertStringNotContainsString('public', self::cacheControl());
        self::assertStringNotContainsString('max-age', self::cacheControl());
    }

    private static function assertResponseCarriesNoCacheTagHeader(): void
    {
        foreach (self::CACHE_TAG_HEADERS as $header) {
            self::assertResponseNotHasHeader($header);
        }
    }
}
