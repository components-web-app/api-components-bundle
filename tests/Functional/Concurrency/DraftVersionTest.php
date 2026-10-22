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

final class DraftVersionTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use ConcurrencyTrait;
    use MercureTrait;
    use PublishableTrait;

    public function test_a_new_draft_starts_at_version_one_and_leaves_the_published_version(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::setVersionInDatabase($published, 4);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'draft'], ['If-Match' => '"4"']);

        self::assertResponseStatusCodeSame(200);
        self::assertNotSame(self::iriOf($published), self::bodyOf($response)['@id']);
        self::assertSame(1, self::versionOf($response));
        self::assertSame('"1"', $response->getHeaders()['etag'][0] ?? null);
        self::assertSame(4, self::versionInDatabase($published));
    }

    public function test_an_if_match_for_a_live_row_whose_write_goes_to_an_existing_draft_at_the_same_version_is_412_with_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'mine'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(1, self::versionOf($response));
        self::assertSame('"1"', $response->getHeaders(false)['etag'][0] ?? null);
        self::assertSame(1, self::versionInDatabase($draft));
    }

    public function test_an_if_match_for_a_live_row_whose_write_goes_to_an_existing_draft_at_another_version_is_412_with_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'mine'], ['If-Match' => '"7"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(7, self::versionOf($response));
    }

    public function test_a_patch_to_the_live_iri_without_if_match_still_edits_the_existing_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'mine']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(2, self::versionOf($response));
    }

    public function test_a_patch_to_the_drafts_iri_whose_if_match_is_the_drafts_version_edits_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['reference' => 'mine'], ['If-Match' => '"7"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame('mine', self::bodyOf($response)['reference']);
        self::assertSame(8, self::versionOf($response));
    }

    public function test_a_patch_to_the_drafts_iri_with_a_stale_if_match_is_412(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['reference' => 'mine'], ['If-Match' => '"6"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(7, self::versionOf($response));
    }

    public function test_publishing_through_the_drafts_iri_raises_the_published_version(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => 'now'], ['If-Match' => '"7"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($published), self::bodyOf($response)['@id']);
        self::assertSame(2, self::versionOf($response));
        self::assertResourceNotExists(self::iriOf($draft));
    }

    public function test_a_publish_with_a_stale_if_match_is_412_and_publishes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => 'now'], ['If-Match' => '"6"']);

        self::assertResponseStatusCodeSame(412);
        self::assertResourceExists(self::iriOf($draft));
    }

    public function test_the_loser_of_the_first_edit_race_is_412_with_the_winners_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::insertDraftBeforeTheNextWrite($published, 'winner', 2);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'loser'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame('winner', self::bodyOf($response)['reference']);
        self::assertSame(2, self::versionOf($response));
    }

    public function test_the_loser_of_the_first_edit_race_without_if_match_is_412(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::insertDraftBeforeTheNextWrite($published, 'winner', 2);

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'loser']);

        self::assertResponseStatusCodeSame(412);
    }

    public function test_a_draft_edit_leaves_the_published_payloads_version_and_puts_the_drafts_on_the_private_update(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        $publishedIri = self::iriOf($published);
        $draftIri = self::iriOf($draft);
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $publishedIri, ['reference' => 'edited']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, self::mercureUpdateData(self::mercureUpdateFor($draftIri, true))['_metadata']['version'] ?? null);
        self::assertSame([], self::mercureUpdatesFor($publishedIri));
        $client->getCookieJar()->clear();
        self::assertSame(1, self::versionOf(self::requestJsonLd($client, 'GET', $publishedIri)));
    }
}
