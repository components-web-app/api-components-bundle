<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Mercure;

use ApiPlatform\Test\Client;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CookieTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Symfony\Component\Mercure\Update;

final class MercurePublicUpdatesTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use CookieTrait;
    use MercureTrait;
    use PublishableTrait;
    use SiteTrait;

    public function test_an_admin_edit_to_a_position_whose_component_has_a_draft_publishes_the_published_component_publicly_and_the_draft_privately(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createRoutedPageWhoseGroupHoldsAPublishedComponent('/mercure', true);
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $iris['position'], ['componentGroup' => $iris['other_component_group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseCarriesNoCacheTags();
        self::assertStringContainsString('private', (string) self::getClient()->getResponse()->headers->get('cache-control'));
        self::assertResponseHasNoCookie('__Secure-mercure_access_token');
        self::assertPublishedPubliclyAndThenPrivately($iris['position']);
        self::assertSame($iris['published_component'], self::mercureUpdateData(self::mercureUpdateFor($iris['position'], false))['component'] ?? null);
        self::assertSame($iris['draft_component'], self::mercureUpdateData(self::mercureUpdateFor($iris['position'], true))['component'] ?? null);
        self::assertNoPublicMercureUpdateContainsIri($iris['draft_component']);
        self::assertPublicUpdateEqualsAnAnonymousGet($client, $iris['position']);
    }

    public function test_an_admin_editing_a_page_that_no_route_reaches_publishes_nothing_publicly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $page = self::newPage('unrouted-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = self::newLayout('unrouted-layout');
        self::persistAndFlush($page->layout, $page);
        $pageIri = self::iriOf($page);
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $pageIri, '{"title": "A secret title"}');

        self::assertResponseStatusCodeSame(200);
        self::assertOnePrivateUpdateOnTheDraftTopic($pageIri);
        self::assertSame('A secret title', self::mercureUpdateData(self::mercureUpdateFor($pageIri, true))['title'] ?? null);
        self::assertNoPublicMercureUpdateContainsIri($pageIri);
    }

    public function test_an_admin_scheduling_a_live_route_publishes_only_a_delete_publicly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $routeIri = self::iriOf(self::createRouteWithPage('/scheduled'));
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $routeIri, '{"liveAt": "2999-01-01T00:00:00+00:00"}');

        self::assertResponseStatusCodeSame(200);
        self::assertPublishedPubliclyAndThenPrivately($routeIri);
        self::assertPublicUpdateIsADelete($routeIri);
        self::assertSame('2999-01-01T00:00:00+00:00', self::mercureUpdateData(self::mercureUpdateFor($routeIri, true))['liveAt'] ?? null);
        self::assertNoPublicMercureUpdateContains('2999');
    }

    public function test_an_admin_rescheduling_a_route_that_is_not_live_yet_publishes_nothing_publicly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $routeIri = self::iriOf(self::createRouteWithPage('/scheduled'));
        self::setRouteLiveAt('/scheduled', new \DateTimeImmutable('2999-01-01 00:00:00'));
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $routeIri, '{"liveAt": "2999-06-01T00:00:00+00:00"}');

        self::assertResponseStatusCodeSame(200);
        self::assertOnePrivateUpdateOnTheDraftTopic($routeIri);
        self::assertNoPublicMercureUpdateContainsIri($routeIri);
    }

    public function test_an_admin_removing_the_route_of_a_live_page_publishes_a_delete_of_the_page_publicly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createRoutedPageWhoseGroupHoldsAPublishedComponent('/unrouted', false);
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $iris['page'], '{"route": null}');

        self::assertResponseStatusCodeSame(200);
        self::assertPublishedPubliclyAndThenPrivately($iris['page']);
        self::assertPublicUpdateIsADelete($iris['page']);
    }

    public function test_a_published_component_moved_to_a_future_publication_date_is_deleted_from_its_public_topic(): void
    {
        $iris = self::createRoutedPageWhoseGroupHoldsAPublishedComponent('/mercure', false);
        HubStub::clearPublished();

        $component = self::findByIri($iris['published_component']);
        \assert($component instanceof DummyPublishableComponent);
        $component->setPublishedAt(new \DateTime('2999-01-01 00:00:00'));
        self::getEntityManager()->flush();

        self::assertPublicUpdateIsADelete($iris['published_component']);
        self::assertSame('2999-01-01T00:00:00+00:00', self::mercureUpdateData(self::mercureUpdateFor($iris['published_component'], true))['publishedAt'] ?? null);
    }

    public function test_a_draft_edit_is_published_only_privately_on_the_draft_topic(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable('2999-12-31T23:59:59+00:00'));
        $draftIri = self::iriOf($draft);
        HubStub::clearPublished();

        self::requestJsonLd($client, 'PATCH', $draftIri, '{"reference": "edited draft"}');

        self::assertResponseStatusCodeSame(200);
        self::assertOnePrivateUpdateOnTheDraftTopic($draftIri);
        self::assertNoPublicMercureUpdateContains('edited draft');
    }

    public function test_a_removed_resource_is_published_as_a_public_delete_and_nothing_else(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageIri = self::iriOf(self::createPage());
        HubStub::clearPublished();

        self::requestJsonLd($client, 'DELETE', $pageIri);

        self::assertResponseStatusCodeSame(204);
        self::assertPublicUpdateIsADelete($pageIri);
        foreach (self::mercureUpdatesFor($pageIri) as $update) {
            self::assertFalse($update->isPrivate(), \sprintf('A private update was published for %s: %s', $pageIri, self::describeMercureUpdates()));
        }
    }

    /**
     * @return array<string, string>
     */
    private static function createRoutedPageWhoseGroupHoldsAPublishedComponent(string $path, bool $withDraft): array
    {
        $page = self::newPage('mercure-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = self::newLayout('mercure-layout');
        $group = self::newComponentGroup('mercure-group');
        $page->addComponentGroup($group);
        $otherGroup = self::newComponentGroup('mercure-other-group');
        $page->addComponentGroup($otherGroup);
        $published = new DummyPublishableComponent();
        $published->reference = 'published';
        $published->setPublishedAt(new \DateTime('-1 day'));
        $position = self::newComponentPosition($group, $published, 0);
        $route = self::newRoute($path)->setPage($page);
        $entities = [$page->layout, $page, $group, $otherGroup, $published, $position, $route];
        $draft = null;
        if ($withDraft) {
            $draft = new DummyPublishableComponent();
            $draft->reference = 'draft';
            $draft->setPublishedResource($published);
            $entities[] = $draft;
        }
        self::persistAndFlush(...$entities);

        return array_filter([
            'route' => self::iriOf($route),
            'page' => self::iriOf($page),
            'component_group' => self::iriOf($group),
            'other_component_group' => self::iriOf($otherGroup),
            'position' => self::iriOf($position),
            'published_component' => self::iriOf($published),
            'draft_component' => null === $draft ? null : self::iriOf($draft),
        ]);
    }

    private static function assertPublishedPubliclyAndThenPrivately(string $iri): void
    {
        $shape = array_map(static fn (Update $update): string => ($update->isPrivate() ? 'private ' : 'public ') . implode(', ', self::mercureTopicsWithoutOrigin($update)), self::mercureUpdatesFor($iri));

        self::assertSame(['public ' . $iri, 'private ' . $iri . '?draft=1'], $shape);
    }

    private static function assertOnePrivateUpdateOnTheDraftTopic(string $iri): void
    {
        $updates = self::mercureUpdatesFor($iri);

        self::assertCount(1, $updates, self::describeMercureUpdates());
        self::assertTrue($updates[0]->isPrivate(), self::describeMercureUpdates());
        self::assertSame([$iri . '?draft=1'], self::mercureTopicsWithoutOrigin($updates[0]));
    }

    private static function assertPublicUpdateIsADelete(string $iri): void
    {
        $update = self::mercureUpdateFor($iri, false);

        self::assertSame([$iri], self::mercureTopicsWithoutOrigin($update));
        self::assertSame(json_encode(['@id' => $iri]), $update->getData());
    }

    private static function assertNoPublicMercureUpdateContainsIri(string $iri): void
    {
        self::assertNoPublicMercureUpdateContains(json_encode($iri, \JSON_UNESCAPED_SLASHES));
        self::assertNoPublicMercureUpdateContains(json_encode($iri));
    }

    private static function assertPublicUpdateEqualsAnAnonymousGet(Client $client, string $iri): void
    {
        $published = self::mercureUpdateData(self::mercureUpdateFor($iri, false));
        $browser = $client->getKernelBrowser();
        $browser->getCookieJar()->clear();
        $browser->request('GET', $iri, [], [], ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => '']);
        $response = $browser->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::withoutBlankNodeIds($published), self::withoutBlankNodeIds(json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)));
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function withoutBlankNodeIds(array $data): array
    {
        foreach ($data as $key => $value) {
            if ('@id' === $key && \is_string($value) && str_starts_with($value, '/.well-known/genid/')) {
                unset($data[$key]);
            } elseif (\is_array($value)) {
                $data[$key] = self::withoutBlankNodeIds($value);
            }
        }

        return $data;
    }
}
