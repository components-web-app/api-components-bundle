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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;

final class PurgeRenderedHtmlOperationTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;

    private const array JSON_LD = ['accept' => 'application/ld+json', 'content-type' => 'application/ld+json'];

    public function test_an_administrator_purges_the_rendered_html_tag_and_only_that_tag(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurgedTimes('cwa-html', 1);
        self::assertOnlyCacheTagsPurged(['cwa-html']);
    }

    public function test_a_request_body_naming_other_tags_cannot_widen_the_purge(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD, 'body' => '{"tags": ["/_/layouts", "manifest:/_/pages/1"], "tag": "/_/routes"}']);

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['cwa-html']);
    }

    public function test_a_query_string_naming_other_tags_cannot_widen_the_purge(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_/rendered_html/purge?tags[]=/_/layouts&tags[]=manifest:/_/pages/1&tag=/_/routes', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(204);
        self::assertOnlyCacheTagsPurged(['cwa-html']);
    }

    public function test_the_operation_cannot_be_reached_with_get(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('GET', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(405);
        self::assertCacheTagNotPurged('cwa-html');
    }

    public function test_a_user_without_the_administrator_role_is_refused_by_the_operations_own_security(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $client->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(403);
        self::assertCacheTagNotPurged('cwa-html');
    }

    public function test_an_anonymous_request_is_refused_by_the_test_applications_firewall_before_the_operation_is_reached(): void
    {
        static::createClient()->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(401);
        self::assertCacheTagNotPurged('cwa-html');
    }

    public function test_repeating_the_purge_is_safe_and_each_call_purges_once(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $client->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurgedTimes('cwa-html', 1);

        $client->request('POST', '/_/rendered_html/purge', ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurgedTimes('cwa-html', 1);
        self::assertOnlyCacheTagsPurged(['cwa-html']);
    }
}
