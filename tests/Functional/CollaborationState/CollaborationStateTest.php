<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\CollaborationState;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\CollaborationState;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CollaborationStateTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use MercureTrait;
    use PublishableTrait;
    use ResourceTrait;
    use SiteTrait;

    private const string COLLECTION = '/_/collaboration_states';

    public function test_an_admin_creates_a_state_for_a_component_with_the_agreed_shape(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::iriOf(self::createComponent());

        $response = self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => $componentIri, 'field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('collaboration_state.schema.json');
        self::assertJsonNodeSame('component', $componentIri);
        self::assertJsonNodeNotExists('pageData');
        self::assertJsonNodeSame('field', 'html');
        self::assertJsonNodeSame('state', 'AQID');
        self::assertJsonNodeSame('htmlHash', 'abc');
        self::assertJsonNodeSame('_metadata.version', 1);
        self::assertSame('"1"', $response->getHeaders(false)['etag'][0] ?? null);
        self::assertNeverStored($response);
    }

    public function test_an_admin_creates_a_state_for_page_data(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageDataIri = self::iriOf(self::createPageDataWithRoute(null)['pageData']);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['pageData' => $pageDataIri, 'field' => 'title', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('collaboration_state.schema.json');
        self::assertJsonNodeSame('pageData', $pageDataIri);
        self::assertJsonNodeNotExists('component');
    }

    public function test_a_state_for_both_a_component_and_page_data_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::iriOf(self::createComponent());
        $pageDataIri = self::iriOf(self::createPageDataWithRoute(null)['pageData']);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => $componentIri, 'pageData' => $pageDataIri, 'field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    public function test_a_state_for_neither_a_component_nor_page_data_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    public function test_a_component_iri_that_does_not_exist_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        $iri = self::iriOf($component);
        $manager = static::getEntityManager();
        $manager->getConnection()->delete($manager->getClassMetadata(AbstractComponent::class)->getTableName(), ['id' => (string) $component->getId()]);

        $response = self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => $iri, 'field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertContains($response->getStatusCode(), [400, 422]);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    public function test_a_second_state_for_the_same_component_and_field_is_422_on_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'html');

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => self::iriOf($component), 'field' => 'html', 'state' => 'BBBB', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertSame(1, self::countEntities(CollaborationState::class));
    }

    public function test_a_second_state_for_the_same_page_data_and_field_is_422_on_page_data(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageData = self::createPageDataWithRoute(null)['pageData'];
        self::createState($pageData, 'title');

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['pageData' => self::iriOf($pageData), 'field' => 'title', 'state' => 'BBBB', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'pageData');
    }

    public function test_the_same_component_holds_one_state_per_field(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'html');

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => self::iriOf($component), 'field' => 'title', 'state' => 'BBBB', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, self::countEntities(CollaborationState::class));
    }

    public function test_a_state_larger_than_8_mb_of_base64_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::iriOf(self::createComponent());

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => $componentIri, 'field' => 'html', 'state' => str_repeat('A', CollaborationState::STATE_MAX_LENGTH + 1), 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'state');
    }

    public function test_a_state_of_8_mb_of_base64_is_stored_whole(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $componentIri = self::iriOf(self::createComponent());
        $state = str_repeat('A', CollaborationState::STATE_MAX_LENGTH);

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => $componentIri, 'field' => 'html', 'state' => $state, 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        $stored = static::getEntityManager()->getRepository(CollaborationState::class)->findAll();
        self::assertCount(1, $stored);
        self::assertSame($state, $stored[0]->getState());
    }

    public function test_the_collection_filtered_by_component_and_field_returns_that_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        $other = self::createComponent();
        $stateIri = self::iriOf(self::createState($component, 'html'));
        self::createState($component, 'title');
        self::createState($other, 'html');

        $response = $client->request('GET', self::COLLECTION . '?' . http_build_query(['component' => self::iriOf($component), 'field' => 'html']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].@id', $stateIri);
        self::assertEachJsonItemMatchesSchemaFile('collaboration_state.schema.json', json_encode(self::lastJson()['member'], \JSON_THROW_ON_ERROR));
        self::assertNeverStored($response);
    }

    public function test_the_collection_filtered_by_page_data_and_field_returns_that_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageData = self::createPageDataWithRoute(null)['pageData'];
        $stateIri = self::iriOf(self::createState($pageData, 'title'));
        self::createState(self::createComponent(), 'title');

        $client->request('GET', self::COLLECTION . '?' . http_build_query(['pageData' => self::iriOf($pageData), 'field' => 'title']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].@id', $stateIri);
        self::assertJsonNodeSame('member[0].pageData', self::iriOf($pageData));
    }

    public function test_the_collection_is_empty_when_there_is_no_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'title');

        $client->request('GET', self::COLLECTION . '?' . http_build_query(['component' => self::iriOf($component), 'field' => 'html']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('member', []);
    }

    public function test_a_patch_whose_if_match_is_the_current_version_replaces_the_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $stateIri = self::iriOf(self::createState(self::createComponent(), 'html'));

        $response = self::requestJsonLd($client, 'PATCH', $stateIri, ['state' => 'BBBB', 'htmlHash' => 'def'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('state', 'BBBB');
        self::assertJsonNodeSame('htmlHash', 'def');
        self::assertJsonNodeSame('_metadata.version', 2);
        self::assertSame('"2"', $response->getHeaders(false)['etag'][0] ?? null);
    }

    public function test_a_patch_with_a_stale_if_match_is_412_with_the_current_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $stateIri = self::iriOf(self::createState(self::createComponent(), 'html'));
        self::requestJsonLd($client, 'PATCH', $stateIri, ['state' => 'THEIRS'], ['If-Match' => '"1"']);

        $response = self::requestJsonLd($client, 'PATCH', $stateIri, ['state' => 'MINE'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame('"2"', $response->getHeaders(false)['etag'][0] ?? null);
        self::assertJsonNodeSame('@id', $stateIri);
        self::assertJsonNodeSame('state', 'THEIRS');
        self::assertJsonNodeSame('_metadata.version', 2);
        self::assertNeverStored($response);
    }

    public function test_a_delete_removes_the_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $stateIri = self::iriOf(self::createState(self::createComponent(), 'html'));

        $client->request('DELETE', $stateIri);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function provideOperations(): iterable
    {
        yield 'the collection' => ['GET', 'collection', false];
        yield 'an item' => ['GET', 'item', false];
        yield 'a create' => ['POST', 'collection', true];
        yield 'a patch' => ['PATCH', 'item', true];
        yield 'a delete' => ['DELETE', 'item', false];
    }

    #[DataProvider('provideOperations')]
    public function test_a_signed_in_user_who_is_not_an_admin_is_forbidden(string $method, string $target, bool $withBody): void
    {
        $client = static::createClient();
        $component = self::createComponent();
        $stateIri = self::iriOf(self::createState($component, 'html'));
        self::loginAsUser($client);

        self::requestJsonLd($client, $method, 'item' === $target ? $stateIri : self::COLLECTION, $withBody ? ['component' => self::iriOf($component), 'field' => 'title', 'state' => 'BBBB', 'htmlHash' => 'abc'] : null);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, self::countEntities(CollaborationState::class));
    }

    #[DataProvider('provideOperations')]
    public function test_an_anonymous_caller_is_refused(string $method, string $target, bool $withBody): void
    {
        $client = static::createClient();
        $component = self::createComponent();
        $stateIri = self::iriOf(self::createState($component, 'html'));

        self::requestJsonLd($client, $method, 'item' === $target ? $stateIri : self::COLLECTION, $withBody ? ['component' => self::iriOf($component), 'field' => 'title', 'state' => 'BBBB', 'htmlHash' => 'abc'] : null);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(1, self::countEntities(CollaborationState::class));
    }

    public function test_a_state_posted_for_a_draft_of_a_published_component_is_stored_under_the_published_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => self::iriOf($draft), 'field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('component', self::iriOf($published));
        self::assertSame(self::iriOf($published), self::iriOf(self::storedStates()[0]->getComponent()));
    }

    public function test_a_state_posted_for_a_published_component_with_a_draft_is_stored_under_the_published_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published] = self::createPublishedWithDraft();

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => self::iriOf($published), 'field' => 'html', 'state' => 'AQID', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('component', self::iriOf($published));
        self::assertSame(self::iriOf($published), self::iriOf(self::storedStates()[0]->getComponent()));
    }

    public function test_a_draft_and_its_published_component_share_one_state_per_field(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::createState($published, 'html');

        self::requestJsonLd($client, 'POST', self::COLLECTION, ['component' => self::iriOf($draft), 'field' => 'html', 'state' => 'BBBB', 'htmlHash' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertSame(1, self::countEntities(CollaborationState::class));
    }

    public function test_the_published_components_state_is_found_by_its_iri_while_a_draft_exists(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published] = self::createPublishedWithDraft();
        $stateIri = self::iriOf(self::createState($published, 'html'));

        $client->request('GET', self::COLLECTION . '?' . http_build_query(['component' => self::iriOf($published), 'field' => 'html']));

        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].@id', $stateIri);
    }

    public function test_publishing_a_draft_keeps_the_published_components_state(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        self::createState($published, 'html');

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertResourceNotExists(self::iriOf($draft));
        self::assertSame(self::iriOf($published), self::iriOf(self::storedStates()[0]->getComponent()));
    }

    public function test_a_never_published_draft_keeps_its_state_when_first_published(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishable();
        self::createState($draft, 'html');

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($draft), self::iriOf(self::storedStates()[0]->getComponent()));
    }

    public function test_deleting_a_component_deletes_its_states(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'html');
        self::createState($component, 'title');
        self::createState(self::createComponent(), 'html');

        $client->request('DELETE', self::iriOf($component));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, self::countEntities(CollaborationState::class));
    }

    public function test_deleting_page_data_deletes_its_states(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageData = self::createPageDataWithRoute(null)['pageData'];
        self::createState($pageData, 'title');

        $client->request('DELETE', self::iriOf($pageData));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    public function test_deleting_an_orphaned_component_deletes_its_states(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'html');

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', ['iris' => [self::iriOf($component)]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('deleted.components', [self::iriOf($component)]);
        self::assertSame(0, self::countEntities(CollaborationState::class));
    }

    public function test_a_state_is_not_an_orphan_and_does_not_make_its_component_used(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent();
        self::createState($component, 'html');

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        $client->request('GET', '/_/orphaned_resources');

        self::assertJsonNodeSame('components', [self::iriOf($component)]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideWrites(): iterable
    {
        yield 'a create' => ['POST'];
        yield 'a patch' => ['PATCH'];
        yield 'a delete' => ['DELETE'];
    }

    #[DataProvider('provideWrites')]
    public function test_a_state_write_publishes_nothing_and_purges_nothing_of_its_component(string $method): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createPublishable(new \DateTimeImmutable('-1 hour'));
        $componentIri = self::iriOf($component);
        $existing = 'POST' === $method ? null : self::iriOf(self::createState($component, 'html'));
        HubStub::clearPublished();

        self::requestJsonLd($client, $method, $existing ?? self::COLLECTION, 'DELETE' === $method ? null : ['component' => $componentIri, 'field' => 'html', 'state' => 'BBBB', 'htmlHash' => 'abc']);

        self::assertResponseIsSuccessful();
        self::assertNoMercureUpdatePublished();
        self::assertCacheTagNotPurged($componentIri);
    }

    private static function createComponent(): DummyComponent
    {
        $component = new DummyComponent();
        static::persistAndFlush($component);

        return $component;
    }

    private static function createState(AbstractComponent|AbstractPageData $resource, string $field): CollaborationState
    {
        $manager = static::getEntityManager();
        $state = new CollaborationState();
        if ($resource instanceof AbstractComponent) {
            $state->setComponent($manager->find($resource::class, $resource->getId()));
        } else {
            $state->setPageData($manager->find($resource::class, $resource->getId()));
        }
        $state->setField($field);
        $state->setState('AQID');
        $state->setHtmlHash('hash');
        static::persistAndFlush($state);

        return $state;
    }

    /**
     * @return list<CollaborationState>
     */
    private static function storedStates(): array
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->getRepository(CollaborationState::class)->findAll();
    }

    private static function assertNeverStored(ResponseInterface $response): void
    {
        $cacheControl = $response->getHeaders(false)['cache-control'][0] ?? '';
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }
}
