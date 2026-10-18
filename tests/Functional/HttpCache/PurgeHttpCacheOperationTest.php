<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\HttpCache;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\MockClientCallback;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CwaFixtureTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PurgeHttpCacheOperationTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;
    use CwaFixtureTrait;

    private const array JSON_LD = ['accept' => 'application/ld+json', 'content-type' => 'application/ld+json'];

    public function test_a_purger_that_cannot_flush_is_refused_rather_than_reporting_success(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::purge($client);

        self::assertResponseStatusCodeSame(501);
        self::assertNoCachePurgeRequestSent();
    }

    public function test_the_operation_cannot_be_reached_with_get(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('GET', '/_/http_cache/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(405);
    }

    public function test_a_user_without_the_administrator_role_is_refused_by_the_operations_own_security(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::purge($client);

        self::assertResponseStatusCodeSame(403);
        self::assertNoCachePurgeRequestSent();
    }

    public function test_an_anonymous_request_is_refused_by_the_test_applications_firewall_before_the_operation_is_reached(): void
    {
        self::purge(static::createClient());

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_body_naming_tags_purges_those_tags_and_nothing_else_and_never_flushes(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::purge($client, '{"tags": ["/_/layouts", "cwa-html"]}');

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertOnlyCacheTagsPurged(['/_/layouts', 'cwa-html']);
    }

    public function test_a_body_naming_paths_purges_the_tag_a_write_to_the_route_at_each_path_purges(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::buildSite(static function (CwaFixtureBuilder $cwa): void {
            $cwa->layout('primary', 'Primary');
            $cwa->page('team', 'Primary', 'primary', route: '/about-us/team');
        });

        self::purge($client, '{"paths": ["/about-us/team", "/"]}');

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['/_/routes//about-us/team', '/_/routes//']);
    }

    public function test_paths_and_tags_can_be_sent_together_and_the_union_is_purged_once(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::purge($client, '{"paths": ["/about-us"], "tags": ["cwa-html", "/_/routes//about-us"]}');

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['/_/routes//about-us', 'cwa-html']);
        self::assertCacheTagPurgedTimes('/_/routes//about-us', 1);
    }

    public function test_the_api_routes_import_prefix_is_part_of_the_tag_a_path_resolves_to(): void
    {
        static::bootKernel(['routePrefix' => '/_api']);
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_api/_/http_cache/purge', ['headers' => self::JSON_LD, 'body' => '{"paths": ["/about-us"]}']);

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['/_api/_/routes//about-us']);
    }

    public function test_a_scoped_purge_that_fails_is_a_502(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        MockClientCallback::setCacheUnreachable(true);

        self::purge($client, '{"tags": ["cwa-html"]}');

        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('application/problem+json', (string) self::getClient()->getResponse()->headers->get('content-type'));
    }

    #[DataProvider('bodiesThatAreNotAScopedPurge')]
    public function test_a_body_that_is_not_a_scoped_purge_is_refused_and_neither_purges_nor_flushes(string $body, string $propertyPath): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::purge($client, $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($propertyPath, $response->toArray(false)['violations'][0]['propertyPath']);
        self::assertNoCachePurgeRequestSent();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bodiesThatAreNotAScopedPurge(): iterable
    {
        yield 'an empty object' => ['{}', ''];
        yield 'an empty array' => ['[]', ''];
        yield 'null' => ['null', ''];
        yield 'a string' => ['"/_/layouts"', ''];
        yield 'invalid JSON' => ['{"tags": ["cwa-html"]', ''];
        yield 'an unknown key' => ['{"tag": ["cwa-html"]}', 'tag'];
        yield 'all beside tags' => ['{"tags": ["cwa-html"], "all": true}', 'all'];
        yield 'no tags' => ['{"tags": []}', 'tags'];
        yield 'no paths' => ['{"paths": []}', 'paths'];
        yield 'tags as a string' => ['{"tags": "cwa-html"}', 'tags'];
        yield 'tags as an object' => ['{"tags": {"a": "cwa-html"}}', 'tags'];
        yield 'paths as null' => ['{"paths": null}', 'paths'];
        yield 'a tag with a comma' => ['{"tags": ["cwa-html", "/one,/two"]}', 'tags[1]'];
        yield 'a tag with a space' => ['{"tags": ["/one /two"]}', 'tags[0]'];
        yield 'an empty tag' => ['{"tags": [""]}', 'tags[0]'];
        yield 'a tag that is not a string' => ['{"tags": [1]}', 'tags[0]'];
        yield 'a relative path' => ['{"paths": ["about-us"]}', 'paths[0]'];
        yield 'a path that is not a string' => ['{"paths": ["/about-us", 5]}', 'paths[1]'];
        yield 'a valid path beside an empty tag' => ['{"paths": ["/about-us"], "tags": [""]}', 'tags[0]'];
    }

    public function test_a_path_whose_route_tag_the_purge_header_would_split_is_refused_before_anything_is_purged(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::purge($client, '{"paths": ["/one,two"]}');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('paths[0]', $response->toArray(false)['violations'][0]['propertyPath']);
        self::assertNoCachePurgeRequestSent();
    }

    public function test_a_user_without_the_administrator_role_cannot_purge_tags(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::purge($client, '{"tags": ["cwa-html"]}');

        self::assertResponseStatusCodeSame(403);
        self::assertNoCachePurgeRequestSent();
    }

    public function test_a_scoped_purge_sent_as_plain_json_is_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_/http_cache/purge', ['headers' => ['accept' => 'application/ld+json', 'content-type' => 'application/json'], 'body' => '{"tags": ["cwa-html"]}']);

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['cwa-html']);
    }

    private static function purge(Client $client, ?string $body = null): ResponseInterface
    {
        return $client->request('POST', '/_/http_cache/purge', ['headers' => self::JSON_LD] + (null === $body ? [] : ['body' => $body]));
    }
}
