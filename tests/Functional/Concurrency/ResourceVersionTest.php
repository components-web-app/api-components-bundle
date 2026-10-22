<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Concurrency;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class ResourceVersionTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use ConcurrencyTrait;
    use MercureTrait;
    use PublishableTrait;
    use SiteTrait;

    public function test_a_created_component_starts_at_version_one(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::requestJsonLd($client, 'POST', '/component/dummy_publishable_components', ['reference' => 'new']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, self::versionOf($response));
    }

    public function test_every_write_of_a_component_raises_its_version(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent());

        self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'first']);
        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'second']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, self::versionOf($response));
    }

    public function test_every_write_of_page_data_raises_its_version(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createPageDataWithRoute('/versioned')['pageData']);

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['title' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, self::versionOf($response));
    }

    public function test_a_signed_in_response_carries_the_version_as_a_strong_etag(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent('original', 4));

        $response = self::requestJsonLd($client, 'GET', $iri);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('"4"', $response->getHeaders()['etag'][0] ?? null);
    }

    public function test_a_write_response_carries_the_new_version_as_its_etag(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent());

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'edited']);

        self::assertSame('"2"', $response->getHeaders()['etag'][0] ?? null);
    }

    public function test_an_anonymous_response_keeps_the_content_hash_etag(): void
    {
        $client = static::createClient();
        $iri = self::iriOf(self::createPublishable(new \DateTimeImmutable('-1 hour')));

        $response = self::requestJsonLd($client, 'GET', $iri);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, self::versionOf($response));
        self::assertSame('"' . hash('xxh3', $response->getContent()) . '"', $response->getHeaders()['etag'][0] ?? null);
    }

    public function test_the_version_is_neither_a_readable_nor_a_writable_property(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($component), ['reference' => 'edited', 'resourceVersion' => 99]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('resourceVersion', self::bodyOf($response));
        self::assertSame(2, self::versionInDatabase($component));
    }

    public function test_a_write_without_a_client_id_puts_the_version_on_both_mercure_updates(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        HubStub::clearPublished();

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        $draft = self::mercureUpdateData(self::mercureUpdateFor(self::bodyOf($response)['@id'], true));
        self::assertSame(2, $draft['_metadata']['version'] ?? null);
        self::assertArrayNotHasKey('lastWrite', $draft['_metadata']);
        $publishedUpdates = array_filter(self::mercureUpdatesFor(self::iriOf($published)), static fn ($update): bool => !str_contains($update->getTopics()[0], '?'));
        self::assertNotEmpty($publishedUpdates, self::describeMercureUpdates());
        foreach ($publishedUpdates as $update) {
            self::assertSame(1, self::mercureUpdateData($update)['_metadata']['version'] ?? null);
        }
    }

    public function test_the_public_update_of_a_published_resource_carries_its_own_version(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createPublishable(new \DateTimeImmutable('-1 hour')));
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $iri . '?published=true', ['reference' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, self::mercureUpdateData(self::mercureUpdateFor($iri, false))['_metadata']['version'] ?? null);
    }

    public function test_a_write_without_if_match_raises_the_version_above_one_committed_since_it_was_read(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::setVersionBeforeTheNextFlush($component, 5);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($component), ['reference' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(6, self::versionOf($response));
        self::assertSame(6, self::versionInDatabase($component));
    }
}
