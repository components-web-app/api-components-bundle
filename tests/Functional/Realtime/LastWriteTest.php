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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class LastWriteTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use MercureTrait;
    use ResourceTrait;
    use SiteTrait;

    private const string CLIENT_ID = '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b';

    public function test_a_write_carrying_a_client_id_echoes_it_in_the_writers_response_and_the_private_update_only(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $layoutIri, ['reference' => 'edited'], ['X-CWA-Client-Id' => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        $lastWrite = ['clientId' => self::CLIENT_ID, 'userId' => self::iriOf($admin)];
        self::assertSame($lastWrite, $response->toArray()['_metadata']['lastWrite'] ?? null);
        self::assertSame($lastWrite, self::mercureUpdateData(self::mercureUpdateFor($layoutIri, true))['_metadata']['lastWrite'] ?? null);
        self::assertSame([$layoutIri . '?draft=1'], self::mercureTopicsWithoutOrigin(self::mercureUpdateFor($layoutIri, true)));
        $public = self::mercureUpdateFor($layoutIri, false);
        self::assertSame('edited', self::mercureUpdateData($public)['reference'] ?? null);
        self::assertArrayNotHasKey('lastWrite', self::mercureUpdateData($public)['_metadata'] ?? []);
        self::assertNoPublicMercureUpdateContains(self::CLIENT_ID);
    }

    public function test_a_write_without_a_client_id_echoes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $layoutIri, ['reference' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('lastWrite', $response->toArray()['_metadata'] ?? []);
        self::assertNotEmpty(self::mercureUpdatesFor($layoutIri));
        foreach (self::mercureUpdatesFor($layoutIri) as $update) {
            self::assertArrayNotHasKey('lastWrite', self::mercureUpdateData($update)['_metadata'] ?? []);
        }
    }

    public function test_a_client_id_that_is_not_a_uuid_is_ignored(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $layoutIri, ['reference' => 'edited'], ['X-CWA-Client-Id' => 'tab-1']);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('lastWrite', $response->toArray()['_metadata'] ?? []);
        self::assertNotEmpty(self::mercureUpdatesFor($layoutIri));
        foreach (self::mercureUpdatesFor($layoutIri) as $update) {
            self::assertArrayNotHasKey('lastWrite', self::mercureUpdateData($update)['_metadata'] ?? []);
        }
    }

    public function test_a_read_carrying_a_client_id_echoes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));

        $response = self::requestJsonLd($client, 'GET', $layoutIri, null, ['X-CWA-Client-Id' => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('lastWrite', $response->toArray()['_metadata'] ?? []);
    }
}
