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

    public function test_a_new_draft_is_versioned_above_its_published_resource(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::setVersionInDatabase($published, 4);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'draft'], ['If-Match' => '"4"']);

        self::assertResponseStatusCodeSame(200);
        self::assertNotSame(self::iriOf($published), self::bodyOf($response)['@id']);
        self::assertSame(5, self::versionOf($response));
        self::assertSame(4, self::versionInDatabase($published));
    }

    public function test_if_match_is_compared_with_the_draft_when_one_exists(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'mine'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(7, self::versionOf($response));
    }

    public function test_a_patch_whose_if_match_is_the_drafts_version_edits_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'mine'], ['If-Match' => '"7"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($draft), self::bodyOf($response)['@id']);
        self::assertSame(8, self::versionOf($response));
    }

    public function test_publishing_a_draft_raises_the_published_version_above_the_drafts(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['publishedAt' => 'now'], ['If-Match' => '"7"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($published), self::bodyOf($response)['@id']);
        self::assertSame(8, self::versionOf($response));
    }

    public function test_a_publish_with_a_stale_if_match_is_412_and_publishes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionInDatabase($draft, 7);

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['publishedAt' => 'now'], ['If-Match' => '"6"']);

        self::assertResponseStatusCodeSame(412);
        self::assertResourceExists(self::iriOf($draft));
    }

    public function test_a_publish_racing_a_draft_edit_at_commit_is_412(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::setVersionBeforeTheNextWrite($draft, 3);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['publishedAt' => 'now'], ['If-Match' => '"2"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(3, self::versionOf($response));
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
        self::assertSame(3, self::mercureUpdateData(self::mercureUpdateFor($draftIri, true))['_metadata']['version'] ?? null);
        self::assertSame([], self::mercureUpdatesFor($publishedIri));
        $client->getCookieJar()->clear();
        self::assertSame(1, self::versionOf(self::requestJsonLd($client, 'GET', $publishedIri)));
    }

    public function test_a_first_edit_racing_a_write_to_the_published_resource_is_412_and_creates_no_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::setVersionBeforeTheNextWrite($published, 2);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'stale'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(self::iriOf($published), self::bodyOf($response)['@id']);
        self::assertSame(2, self::versionOf($response));
    }

    public function test_a_new_draft_is_versioned_above_the_published_version_as_stored(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        self::setVersionBeforeTheNextWrite($published, 4);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['reference' => 'draft']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(5, self::versionOf($response));
    }
}
