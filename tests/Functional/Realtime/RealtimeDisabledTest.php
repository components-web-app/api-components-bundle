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
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CookieTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class RealtimeDisabledTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CookieTrait;
    use MercureTrait;
    use ResourceTrait;
    use SiteTrait;

    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';
    private const string BODY = '{"clientId": "0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b", "event": "presence", "data": {"state": "left"}}';

    public function test_with_the_channel_switched_off_an_admins_event_is_a_404_and_publishes_nothing(): void
    {
        $client = self::clientWithTheChannelOff();
        self::loginAsAdmin($client);
        HubStub::clearPublished();

        $client->request('POST', '/_/realtime', ['headers' => ['accept' => 'application/ld+json', 'content-type' => 'application/json'], 'body' => self::BODY]);

        self::assertResponseStatusCodeSame(404);
        self::assertNoMercureUpdatePublished();
    }

    public function test_with_the_channel_switched_off_an_anonymous_event_is_a_404_too(): void
    {
        $client = self::clientWithTheChannelOff();

        $client->request('POST', '/_/realtime', ['headers' => ['accept' => 'application/ld+json', 'content-type' => 'text/plain;charset=UTF-8'], 'body' => self::BODY]);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_with_the_channel_switched_off_an_admins_mercure_cookie_no_longer_grants_its_topic(): void
    {
        $client = self::clientWithTheChannelOff();
        self::createUser(['ROLE_ADMIN'], 'admin', 'admin@example.com', 'admin-password');

        self::requestJsonLd($client, 'POST', '/login', ['username' => 'admin', 'password' => 'admin-password']);

        self::assertResponseStatusCodeSame(204);
        self::assertMercureCookieGrantsTopicMatching('#/_/layouts/:id\\\\\?draft=1$#');
        self::assertMercureCookieGrantsNoTopicMatching('#/_/realtime#');
    }

    public function test_with_the_channel_switched_off_a_write_still_echoes_its_last_write(): void
    {
        $client = self::clientWithTheChannelOff();
        $admin = self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $layoutIri, ['reference' => 'edited'], ['X-CWA-Client-Id' => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['clientId' => self::CLIENT_ID, 'userId' => self::iriOf($admin)], array_intersect_key($response->toArray()['_metadata']['lastWrite'] ?? [], ['clientId' => true, 'userId' => true]));
        self::assertSame(self::CLIENT_ID, self::mercureUpdateData(self::mercureUpdateFor($layoutIri, true))['_metadata']['lastWrite']['clientId'] ?? null);
    }

    private static function clientWithTheChannelOff(): Client
    {
        static::bootKernel(['realtimeEnabled' => false]);

        return static::createClient();
    }
}
