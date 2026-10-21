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

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Realtime\LastWriteResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
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
        $lastWrite = $response->toArray()['_metadata']['lastWrite'] ?? [];
        self::assertSame(['clientId' => self::CLIENT_ID, 'userId' => self::iriOf($admin), 'at' => $lastWrite['at'] ?? null], $lastWrite);
        self::assertEqualsWithDelta(microtime(true), (float) \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $lastWrite['at'])->format('U.u'), 5);
        self::assertSame($lastWrite, self::mercureUpdateData(self::mercureUpdateFor($layoutIri, true))['_metadata']['lastWrite'] ?? null);
        self::assertSame([$layoutIri . '?draft=1'], self::mercureTopicsWithoutOrigin(self::mercureUpdateFor($layoutIri, true)));
        $public = self::mercureUpdateFor($layoutIri, false);
        self::assertSame('edited', self::mercureUpdateData($public)['reference'] ?? null);
        self::assertArrayNotHasKey('lastWrite', self::mercureUpdateData($public)['_metadata'] ?? []);
        self::assertNoPublicMercureUpdateContains(self::CLIENT_ID);
    }

    public function test_a_post_carrying_a_client_id_echoes_it_in_the_private_update_of_the_created_resource(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'POST', '/component/dummy_publishable_components', ['reference' => 'new'], [LastWriteResolver::HEADER => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(201);
        $iri = $response->toArray()['@id'];
        self::assertEveryUpdateOfTheWriteCarriesItsLastWrite([$iri], self::iriOf($admin), $response->toArray()['_metadata']['lastWrite']['at'] ?? null);
    }

    public function test_a_component_patch_carrying_a_client_id_echoes_it_in_the_private_update(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        $component = new DummyPublishableComponent();
        self::persistAndFlush($component);
        $iri = self::iriOf($component);
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'edited'], [LastWriteResolver::HEADER => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        self::assertEveryUpdateOfTheWriteCarriesItsLastWrite([$iri], self::iriOf($admin), $response->toArray()['_metadata']['lastWrite']['at'] ?? null);
    }

    public function test_a_reorder_carrying_a_client_id_echoes_it_in_the_private_update_of_each_position_whose_sort_value_changed(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        $created = self::createComponentGroupWithComponents(3);
        $group = self::iriOf($created['group']);
        $positions = array_map(static fn (ComponentPosition $position): string => self::iriOf($position), $created['positions']);
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', $group . '/order', ['componentPositions' => [$positions[2], $positions[0], $positions[1]]], [LastWriteResolver::HEADER => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        $before = array_combine($positions, [0, 1, 2]);
        $changed = array_keys(array_filter($response->toArray()['sortValues'], static fn (int $sortValue, string $iri): bool => $before[$iri] !== $sortValue, \ARRAY_FILTER_USE_BOTH));
        self::assertNotEmpty($changed);
        self::assertEveryUpdateOfTheWriteCarriesItsLastWrite($changed, self::iriOf($admin));
    }

    public function test_a_move_carrying_a_client_id_echoes_it_in_the_private_update_of_every_resource_it_publishes(): void
    {
        $client = static::createClient();
        $admin = self::loginAsAdmin($client);
        $from = self::createComponentGroupWithComponents(2);
        $to = self::createComponentGroupWithComponents(1, 'other_collection');
        $position = self::iriOf($from['positions'][0]);
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'POST', self::iriOf($from['components'][0]) . '/move', ['from' => ['componentPosition' => $position], 'to' => ['componentGroup' => self::iriOf($to['group'])]], [LastWriteResolver::HEADER => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(200);
        self::assertEveryUpdateOfTheWriteCarriesItsLastWrite([$position], self::iriOf($admin));
    }

    public function test_a_delete_carrying_a_client_id_publishes_only_the_bare_iri(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        self::requestJsonLd($client, 'DELETE', $iri, null, [LastWriteResolver::HEADER => self::CLIENT_ID]);

        self::assertResponseStatusCodeSame(204);
        self::assertNotEmpty(self::mercureUpdatesFor($iri));
        foreach (self::mercureUpdatesFor($iri) as $update) {
            self::assertSame(['@id' => $iri], self::mercureUpdateData($update));
        }
        self::assertNoPublicMercureUpdateContains(self::CLIENT_ID);
    }

    /**
     * @param list<string> $iris
     */
    private static function assertEveryUpdateOfTheWriteCarriesItsLastWrite(array $iris, string $userIri, ?string $at = null): void
    {
        $private = [];
        $lastWrites = [];
        foreach (self::mercureUpdates() as $update) {
            $metadata = self::mercureUpdateData($update)['_metadata'] ?? [];
            if (!$update->isPrivate()) {
                self::assertArrayNotHasKey('lastWrite', $metadata);
                continue;
            }
            $private[] = parse_url($update->getTopics()[0], \PHP_URL_PATH);
            $lastWrites[] = $metadata['lastWrite'] ?? null;
        }

        foreach ($iris as $iri) {
            self::assertContains($iri, $private, self::describeMercureUpdates());
        }
        $at ??= $lastWrites[0]['at'] ?? null;
        self::assertIsString($at);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}\z/', $at);
        self::assertSame(array_fill(0, \count($lastWrites), ['clientId' => self::CLIENT_ID, 'userId' => $userIri, 'at' => $at]), $lastWrites);
        self::assertNoPublicMercureUpdateContains(self::CLIENT_ID);
    }

    public function test_the_modified_at_of_a_timestamped_resource_cannot_order_edits_within_one_second(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layoutIri = self::iriOf(self::createLayout('last-write', 'CwaLayoutPrimary'));
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $layoutIri, ['reference' => 'edited'], [LastWriteResolver::HEADER => self::CLIENT_ID]);

        $draft = self::mercureUpdateData(self::mercureUpdateFor($layoutIri, true));
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\z/', $draft['modifiedAt'] ?? null);
        self::assertMatchesRegularExpression('/\.\d{3}[+-]/', $draft['_metadata']['lastWrite']['at'] ?? null);
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
