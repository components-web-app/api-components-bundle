<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Publishable;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithSecurityGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Symfony\Component\Mercure\Update;

final class PublishableTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheControlTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use ManifestTrait;
    use MercureTrait;
    use PublishableSiteTrait;
    use PublishableTrait;

    private const string COLLECTION = '/component/dummy_publishable_components';

    private const string FUTURE = '2999-12-31T23:59:59+00:00';

    private const string FUTURE_EXPIRES = 'Tue, 31 Dec 2999 23:59:59 GMT';

    private const string PAST = '1970-12-31T23:59:59+00:00';

    public function test_a_user_with_draft_access_gets_a_collection_with_the_draft_resources_instead_of_the_published_ones(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resources = self::createDraftAndPublishedResources(2);

        self::requestJsonLd($client, 'GET', self::COLLECTION);

        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsDraftsInsteadOfPublished(self::jsonNode('member'), $resources);
    }

    public function test_a_user_with_draft_access_gets_a_collection_with_the_published_resources_only_with_the_published_true_filter(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resources = self::createDraftAndPublishedResources(2);

        self::requestJsonLd($client, 'GET', self::COLLECTION . '?published=true');

        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsPublishedOnly(self::jsonNode('member'), $resources);
    }

    public function test_a_user_with_no_draft_access_gets_a_collection_with_the_published_resources_only_without_the_draft_resource_key(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resources = self::createDraftAndPublishedResources(2);

        self::requestJsonLd($client, 'GET', self::COLLECTION);

        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsPublishedOnlyWithoutDraftResources(self::jsonNode('member'), $resources);
    }

    public function test_a_user_with_no_draft_access_gets_no_draft_resources_in_a_collection_with_the_published_false_filter(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resources = self::createDraftAndPublishedResources(2);

        self::requestJsonLd($client, 'GET', self::COLLECTION . '?published=false');

        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsPublishedOnlyWithoutDraftResources(self::jsonNode('member'), $resources);
    }

    public function test_a_user_with_draft_access_creates_a_draft_with_a_null_published_at(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['reference' => 'test', 'publishedAt' => null]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
        self::assertJsonNodeNotExists('publishedAt');
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertSame(1, self::countDraftMercureUpdatesInLastRequest());
    }

    #[DataProvider('publicationDates')]
    public function test_a_user_with_draft_access_sets_the_published_at_date_when_creating_a_resource(string $publishedAt, bool $isPublished): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $publishedAt = self::resolveNow($publishedAt);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['reference' => 'test', 'publishedAt' => $publishedAt]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('publishedAt', $publishedAt);
        self::assertJsonNodeSame('_metadata.publishable.published', $isPublished);
    }

    public function test_a_user_with_no_draft_access_creating_a_resource_with_a_null_published_at_gets_the_published_resource_with_the_date_set(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['reference' => 'test', 'publishedAt' => null]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeExists('publishedAt');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertCount(1, self::mercureUpdatesInLastRequest());
        self::assertSame(0, self::countDraftMercureUpdatesInLastRequest());
    }

    #[DataProvider('publicationDates')]
    public function test_a_user_with_draft_access_to_a_specific_resource_sets_the_published_at_date_when_creating_it(string $publishedAt, bool $isPublished): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $publishedAt = self::resolveNow($publishedAt);

        self::requestJsonLd($client, 'POST', '/component/dummy_publishable_with_security_groups', ['reference' => 'test', 'publishedAt' => $publishedAt]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('publishedAt', $publishedAt);
        self::assertJsonNodeSame('_metadata.publishable.published', $isPublished);
    }

    #[DataProvider('publicationDatesOnly')]
    public function test_an_anonymous_user_creating_a_resource_gets_the_published_resource_with_the_publication_date_set_now(string $publishedAt): void
    {
        $client = static::createClient();
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['reference' => 'test', 'publishedAt' => self::resolveNow($publishedAt)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeExists('_metadata.publishable.publishedAt');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeIsNow('_metadata.publishable.publishedAt', $now);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function publicationDates(): iterable
    {
        yield 'now' => ['now', true];
        yield 'in the past' => ['1970-01-01T00:00:00+00:00', true];
        yield 'in the future' => [self::FUTURE, false];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicationDatesOnly(): iterable
    {
        foreach (self::publicationDates() as $name => [$publishedAt]) {
            yield $name => [$publishedAt];
        }
    }

    public function test_a_user_with_draft_access_getting_a_published_resource_with_a_draft_gets_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($published));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($draft));
        self::assertExpiresHeaderContains(self::FUTURE_EXPIRES);
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertJsonNodeExists('publishedResource');
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
    }

    public function test_a_user_with_draft_access_getting_a_component_position_gets_the_draft_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $position = self::placeInComponentPosition($published);

        self::requestJsonLd($client, 'GET', self::iriOf($position));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', self::iriOf($draft));
    }

    public function test_a_user_without_draft_access_getting_a_component_position_gets_the_published_component(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $position = self::placeInComponentPosition($published);

        self::requestJsonLd($client, 'GET', self::iriOf($position));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', self::iriOf($published));
    }

    public function test_an_anonymous_user_getting_a_component_position_holding_a_published_component_with_a_pending_draft_gets_the_published_component(): void
    {
        $client = static::createClient();
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $position = self::placeInComponentPosition($published);

        self::requestJsonLd($client, 'GET', self::iriOf($position));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', self::iriOf($published));
        self::assertJsonNodeSame('_metadata.staticComponent', self::iriOf($published));
    }

    public function test_an_anonymous_user_is_not_shown_a_component_that_was_never_published_in_a_component_position_but_it_is_tagged_with_it(): void
    {
        $client = static::createClient();
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(null));

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('component');
        self::assertJsonNodeNotExists('_metadata.staticComponent');
        self::assertResponseHasCacheTag(self::iriOf($created['component']));
        self::assertStringContainsString('public', (string) self::getClient()->getResponse()->headers->get('cache-control'));
    }

    public function test_an_anonymous_user_is_not_shown_a_scheduled_component_in_a_component_position_and_it_cannot_be_cached_beyond_its_publication(): void
    {
        $client = static::createClient();
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(60));

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('component');
        self::assertJsonNodeNotExists('_metadata.staticComponent');
        self::assertResponseHasCacheTag(self::iriOf($created['component']));
        self::assertMatchesRegularExpression('/GMT$/', (string) self::getClient()->getResponse()->headers->get('expires'));
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_a_user_with_draft_access_is_shown_a_component_that_was_never_published_in_a_component_position_and_no_shared_cache_stores_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(null));
        $componentIri = self::iriOf($created['component']);

        self::requestJsonLd($client, 'GET', self::iriOf($created['position']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', $componentIri);
        self::assertJsonNodeSame('_metadata.staticComponent', $componentIri);
        $cacheControl = (string) self::getClient()->getResponse()->headers->get('cache-control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }

    public function test_publishing_a_component_that_was_never_published_purges_and_publishes_the_positions_holding_it_and_the_manifest_of_the_page_placing_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(null));
        HubStub::clearPublished();
        $componentIri = self::iriOf($created['component']);
        $positionIri = self::iriOf($created['position']);

        self::requestJsonLd($client, 'PATCH', $componentIri, '{"publishedAt": "now"}');

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($componentIri);
        self::assertCacheTagPurged($positionIri);
        self::assertCacheTagPurged(self::manifestCacheTag(self::iriOf($created['page'])));
        self::assertMercureUpdatePublishedFor($positionIri);
    }

    public function test_editing_the_content_of_a_component_that_was_never_published_purges_neither_the_positions_holding_it_nor_the_manifest_of_the_page_placing_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(null));
        $componentIri = self::iriOf($created['component']);

        self::requestJsonLd($client, 'PATCH', $componentIri, '{"reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($componentIri);
        self::assertCacheTagNotPurged(self::iriOf($created['position']));
        self::assertCacheTagNotPurged(self::manifestCacheTag(self::iriOf($created['page'])));
    }

    public function test_an_anonymous_user_is_not_shown_a_scheduled_page_data_component_in_a_dynamic_position_and_it_cannot_be_cached_beyond_its_publication(): void
    {
        $client = static::createClient();
        $created = self::createPageDataWithPublishableInPageDataPropertyPosition(self::unpublishedComponent(60), '/my-route');

        self::requestJsonLd($client, 'GET', $created['componentPosition'], null, ['path' => '/my-route']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('component');
        self::assertMatchesRegularExpression('/GMT$/', (string) self::getClient()->getResponse()->headers->get('expires'));
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_an_anonymous_user_cannot_cache_the_manifest_of_a_page_placing_a_scheduled_component_beyond_its_publication(): void
    {
        $client = static::createClient();
        $created = self::createRoutedPageWithComponent('/my-route', self::unpublishedComponent(60));

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthNotContains(0, self::iriOf($created['component']));
        self::assertManifestDepthContains(0, self::iriOf($created['position']));
        self::assertMatchesRegularExpression('/GMT$/', (string) self::getClient()->getResponse()->headers->get('expires'));
        self::assertSharedMaxAgeAtMost(60);
    }

    public function test_a_user_getting_a_published_resource_with_a_draft_and_the_published_true_filter_gets_the_published_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($published) . '?published=true');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertExpiresHeaderContains(self::FUTURE_EXPIRES);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
    }

    public function test_a_user_with_draft_access_getting_a_draft_resource_with_the_published_true_filter_gets_a_404(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($draft) . '?published=true');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_draft_whose_publication_date_has_passed_replaces_the_published_resource_and_is_removed(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable('2020-01-01T00:00:00+00:00'));
        $draftIri = self::iriOf($draft);

        self::requestJsonLd($client, 'GET', self::iriOf($published));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertResourceNotExists($draftIri);
        self::assertJsonNodeSame('publishedAt', '2020-01-01T00:00:00+00:00');
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
        self::assertResponseNotHasHeader('expires');
    }

    #[DataProvider('publishedFilterQueryStrings')]
    public function test_a_user_with_no_draft_access_getting_a_published_resource_with_a_draft_gets_the_published_resource(string $queryString): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($published) . $queryString);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
        self::assertExpiresHeaderContains(self::FUTURE_EXPIRES);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publishedFilterQueryStrings(): iterable
    {
        yield 'no query string' => [''];
        yield 'published=false' => ['?published=false'];
    }

    public function test_a_publishable_entity_with_customised_fields_can_be_used(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createCustomPublishable(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($draft));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($draft));
        self::assertExpiresHeaderContains(self::FUTURE_EXPIRES);
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertJsonNodeSame('customPublishedAt', self::FUTURE);
        self::assertArrayNotHasKey('customPublishedResource', self::lastJson());
        self::assertArrayNotHasKey('customDraftResource', self::lastJson());
    }

    #[DataProvider('publishedOrDraft')]
    public function test_a_draft_whose_publication_date_is_reached_is_merged_into_the_published_resource_automatically(string $requested): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::PAST));
        $iris = ['published' => self::iriOf($published), 'draft' => self::iriOf($draft)];

        self::requestJsonLd($client, 'GET', $iris[$requested]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', $iris['published']);
        self::assertJsonNodeSame('reference', 'is_draft');
        self::assertResponseNotHasHeader('expires');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonMatchesSchemaFile('publishable.schema.json');
        self::assertResourceNotExists($iris['draft']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publishedOrDraft(): iterable
    {
        yield 'the draft' => ['draft'];
        yield 'the published resource' => ['published'];
    }

    public function test_a_user_with_draft_access_updating_a_published_resource_creates_and_returns_a_draft_without_the_component_position(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));
        self::placeInComponentPosition($published);
        $publishedIri = self::iriOf($published);

        self::requestJsonLd($client, 'PATCH', $publishedIri, '{"reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('publishedAt');
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeNotExists('componentPositions[0]');
        $reloaded = self::findByIri($publishedIri);
        self::assertInstanceOf(DummyPublishableComponent::class, $reloaded);
        self::assertCount(1, $reloaded->getComponentPositions());
    }

    public function test_a_user_with_draft_access_updating_a_published_resource_with_a_draft_updates_and_returns_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), '{"reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('publishedAt', self::FUTURE);
        self::assertJsonNodeSame('reference', 'updated');
    }

    public function test_a_user_with_draft_access_updating_a_published_resource_with_no_draft_updates_and_returns_a_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), '{"reference": "updated_again"}');

        self::assertResponseStatusCodeSame(200);
        self::assertNull(self::lastJson()['publishedAt'] ?? null);
        self::assertJsonNodeSame('reference', 'updated_again');
    }

    #[DataProvider('pastOrNow')]
    public function test_a_user_with_draft_access_updating_a_published_resource_with_a_publication_date_in_the_past_or_now_is_ignored(string $publishedAt): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), ['publishedAt' => self::resolveNow($publishedAt)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('publishedAt', self::PAST);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pastOrNow(): iterable
    {
        yield 'in the past' => ['1970-01-01T00:00:00+00:00'];
        yield 'now' => ['now'];
    }

    #[DataProvider('mergingUpdates')]
    public function test_a_user_with_draft_access_setting_a_past_or_now_publication_date_with_a_draft_available_merges_the_draft_into_the_published_resource(string $publishedAt, string $requested): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $iris = ['published' => self::iriOf($published), 'draft' => self::iriOf($draft)];
        $publishedAt = self::resolveNow($publishedAt);

        self::requestJsonLd($client, 'PATCH', $iris[$requested], ['publishedAt' => $publishedAt, 'reference' => 'updated']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', $iris['published']);
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeSame('publishedAt', $publishedAt);
        self::assertResourceNotExists($iris['draft']);
        self::assertResponseNotHasHeader('expires');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mergingUpdates(): iterable
    {
        yield 'a past date on the published resource' => ['1970-01-01T00:00:00+00:00', 'published'];
        yield 'now on the draft' => ['now', 'draft'];
    }

    public function test_a_user_with_draft_access_setting_a_future_publication_date_on_a_published_resource_with_a_draft_updates_and_returns_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), '{"publishedAt": "2991-11-11T23:59:59+00:00"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('publishedAt', '2991-11-11T23:59:59+00:00');
        self::assertJsonNodeSame('@id', self::iriOf($draft));
    }

    public function test_publishing_a_draft_with_published_at_now_publishes_it_by_the_servers_clock(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $draftIri = self::iriOf($draft);
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'PATCH', $draftIri, '{"publishedAt": "now", "reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeIsNow('publishedAt', $now);
        self::assertResourceNotExists($draftIri);
    }

    public function test_publishing_a_draft_that_was_never_published_with_published_at_now_publishes_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), '{"publishedAt": "now"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeIsNow('publishedAt', $now);
    }

    public function test_creating_a_resource_with_published_at_now_publishes_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'POST', self::COLLECTION, '{"reference": "test", "publishedAt": "now"}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertJsonNodeIsNow('publishedAt', $now);
    }

    public function test_the_publication_date_of_a_published_resource_cannot_be_changed_to_now(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published) . '?published=true', '{"publishedAt": "now"}');

        self::assertResponseStatusCodeSame(422);
    }

    public function test_a_user_who_cannot_publish_cannot_change_the_publication_date_with_now(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), '{"publishedAt": "now", "reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeSame('publishedAt', self::PAST);
    }

    public function test_a_user_with_no_draft_access_updating_a_published_resource_updates_and_returns_the_published_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $published = self::createPublishable(new \DateTimeImmutable(self::PAST));

        self::requestJsonLd($client, 'PATCH', self::iriOf($published), '{"reference": "updated"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeSame('@id', self::iriOf($published));
    }

    public function test_a_user_with_no_draft_access_cannot_update_a_draft_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), '{"reference": "updated"}');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_the_published_resource_property_cannot_be_modified_through_the_api(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), '{"reference": "updated", "publishedResource": "something_else"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('reference', 'updated');
        self::assertJsonNodeNotExists('publishedResource');
    }

    public function test_any_user_deleting_a_published_resource_with_a_draft_deletes_the_published_resource_and_keeps_the_draft(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($published));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($published));
        self::assertResourceExists(self::iriOf($draft));
    }

    public function test_a_user_with_draft_access_can_delete_a_draft_resource(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($draft));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($draft));
        self::assertResourceExists(self::iriOf($published));
    }

    public function test_a_user_with_draft_access_can_delete_a_draft_with_no_live_variant_using_the_published_false_parameter(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($draft) . '?published=false');

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($draft));
    }

    public function test_a_user_without_normal_draft_access_can_delete_a_component_where_security_groups_allow_it(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE), new DummyPublishableWithSecurityGroups());

        self::deleteResource($client, self::iriOf($draft));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($draft));
    }

    public function test_a_user_with_draft_access_deleting_a_published_resource_deletes_the_draft_instead(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($published));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($draft));
        self::assertResourceExists(self::iriOf($published));
    }

    public function test_a_user_with_draft_access_deleting_a_published_resource_with_the_published_true_query_deletes_the_published_resource(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($published) . '?published=true');

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($published));
        self::assertResourceExists(self::iriOf($draft));
    }

    public function test_a_user_with_no_draft_access_cannot_delete_a_draft_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));

        self::deleteResource($client, self::iriOf($draft));

        self::assertResponseStatusCodeSame(404);
        self::assertResourceExists(self::iriOf($draft));
        self::assertResourceExists(self::iriOf($published));
    }

    public function test_a_published_components_usage_can_be_fetched(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::newPublishable(new \DateTimeImmutable('-10 seconds'));
        self::createPublishableInPageDataAndPosition($published);
        self::createDraftFor($published, new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', self::iriOf($published) . '/usage?published=true');

        self::assertUsage();
    }

    #[DataProvider('publishedOrDraft')]
    public function test_a_draft_components_usage_can_be_fetched(string $requested): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::newPublishable(new \DateTimeImmutable('-10 seconds'));
        self::createPublishableInPageDataAndPosition($published);
        $draft = self::createDraftFor($published, new \DateTimeImmutable(self::FUTURE));
        $iris = ['published' => self::iriOf($published), 'draft' => self::iriOf($draft)];

        self::requestJsonLd($client, 'GET', $iris[$requested] . '/usage');

        self::assertUsage();
    }

    public function test_a_newly_published_draft_inherits_the_component_positions_of_the_old_published_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        self::placeInComponentPosition($published);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), '{"publishedAt": "1970-11-11T23:59:59+00:00"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('componentPositions[0]');
    }

    public function test_deleting_a_published_component_keeps_its_component_position_when_a_draft_exists(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable(self::FUTURE));
        $position = self::placeInComponentPosition($published);

        self::deleteResource($client, self::iriOf($published) . '?published=true');

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists(self::iriOf($position));
        self::assertResourceExists(self::iriOf($draft));
        self::assertResourceNotExists(self::iriOf($published));
    }

    public function test_deleting_a_draft_component_with_no_published_version_deletes_its_component_position(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable(new \DateTimeImmutable(self::FUTURE));
        $position = self::placeInComponentPosition($draft);

        self::deleteResource($client, self::iriOf($draft) . '?published=false');

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($position));
        self::assertResourceNotExists(self::iriOf($draft));
    }

    public function test_a_publishable_components_location_count_is_returned_in_metadata(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::createPublishedComponentInPositions(2);

        self::requestJsonLd($client, 'GET', $componentIri);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.locationCount', 2);
    }

    public function test_a_publishable_component_used_as_a_page_data_property_is_included_in_the_location_count(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime());
        $created = self::createPageDataWithPublishableInPageDataPropertyPosition($component, '/test-page-data');

        self::requestJsonLd($client, 'GET', $created['component']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.locationCount', 1);
    }

    private static function resolveNow(string $value): string
    {
        return 'now' === $value ? gmdate('Y-m-d\TH:i:s+00:00') : $value;
    }

    private static function unpublishedComponent(?int $publishInSeconds): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(null === $publishInSeconds ? null : new \DateTime(\sprintf('+%d seconds', $publishInSeconds)));

        return $component;
    }

    private static function deleteResource(Client $client, string $url): void
    {
        self::requestJsonLd($client, 'DELETE', $url);
    }

    private static function countDraftMercureUpdatesInLastRequest(): int
    {
        $drafts = array_filter(self::mercureUpdatesInLastRequest(), static fn (Update $update): bool => str_ends_with($update->getTopics()[0], '?draft=1'));
        foreach ($drafts as $update) {
            self::assertTrue($update->isPrivate(), 'Draft resource messages must be private.');
        }

        return \count($drafts);
    }

    private static function assertExpiresHeaderContains(string $expected): void
    {
        self::assertStringContainsString($expected, (string) self::getClient()->getResponse()->headers->get('expires'));
    }

    private static function assertUsage(): void
    {
        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('total', 2);
        self::assertJsonNodeSame('positionCount', 1);
        self::assertJsonNodeSame('pageDataCount', 1);
    }
}
