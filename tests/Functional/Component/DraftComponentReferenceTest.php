<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Component;

use ApiPlatform\Test\Client;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class DraftComponentReferenceTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    public function test_a_position_patched_to_a_drafts_iri_holds_the_published_component(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(1);
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'PATCH', $group['positions'][0], ['component' => $pair['draft']]);

        self::assertResponseStatusCodeSame(200);
        self::assertPositionHolds($group['positions'][0], $pair['published']);
    }

    public function test_a_position_embedded_in_a_write_of_a_draft_holds_the_published_component(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(200);
        $positions = self::positionsIn($group['group']);
        self::assertCount(1, $positions);
        self::assertSame($pair['published'], self::iriOf($positions[0]->component ?? self::fail('The position holds no component.')));
    }

    public function test_a_never_published_draft_is_placed_as_it_is(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $draft = self::createNeverPublishedDraft();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $draft]);

        self::assertResponseStatusCodeSame(201);
        self::assertPositionHolds(self::jsonNode('@id'), $draft);
    }

    public function test_publishing_a_draft_placed_by_its_iri_never_empties_the_position(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $pair = self::createPublishedComponentWithDraft();
        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $pair['draft']]);
        self::assertResponseStatusCodeSame(201);
        $position = self::jsonNode('@id');

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, self::countEntities(DummyPublishableComponent::class));
        self::assertPositionHolds($position, $pair['published']);
    }

    public function test_publishing_a_never_published_draft_keeps_it_in_its_position(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $draft = self::createNeverPublishedDraft();
        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $draft]);
        self::assertResponseStatusCodeSame(201);
        $position = self::jsonNode('@id');

        self::requestJsonLd($client, 'PATCH', $draft, ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertPositionHolds($position, $draft);
    }

    public function test_a_page_data_property_written_with_a_drafts_iri_holds_the_published_component(): void
    {
        $client = self::adminClient();
        $pageData = self::iriOf(self::createPageDataWithRoute('/page-data')['pageData']);
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'PATCH', $pageData, ['publishableComponent' => $pair['draft']]);

        self::assertResponseStatusCodeSame(200);
        self::assertPageDataHolds($pageData, $pair['published']);
    }

    public function test_publishing_a_draft_written_to_a_page_data_property_never_empties_the_property(): void
    {
        $client = self::adminClient();
        $site = self::createPageDataWithRoute('/page-data');
        $pageData = self::iriOf($site['pageData']);
        $pair = self::createPublishedComponentWithDraft();
        self::requestJsonLd($client, 'PATCH', $pageData, ['publishableComponent' => $pair['draft']]);
        self::assertResponseStatusCodeSame(200);

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertPageDataHolds($pageData, $pair['published']);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    /**
     * @return array{published: string, draft: string}
     */
    private static function createPublishedComponentWithDraft(): array
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        self::persistAndFlush($published, $draft);

        return ['published' => self::iriOf($published), 'draft' => self::iriOf($draft)];
    }

    private static function createNeverPublishedDraft(): string
    {
        $draft = new DummyPublishableComponent();
        self::persistAndFlush($draft);

        return self::iriOf($draft);
    }

    /**
     * @return list<ComponentPosition>
     */
    private static function positionsIn(string $groupIri): array
    {
        $group = self::findByIri($groupIri);
        self::assertNotNull($group);

        return self::getEntityManager()->getRepository(ComponentPosition::class)->findBy(['componentGroup' => $group]);
    }

    private static function assertPositionHolds(string $positionIri, string $componentIri): void
    {
        $position = self::findByIri($positionIri);
        self::assertInstanceOf(ComponentPosition::class, $position);
        self::assertSame($componentIri, self::iriOf($position->component ?? self::fail('The position holds no component.')));
    }

    private static function assertPageDataHolds(string $pageDataIri, string $componentIri): void
    {
        $pageData = self::findByIri($pageDataIri);
        self::assertInstanceOf(PageDataWithComponent::class, $pageData);
        self::assertSame($componentIri, self::iriOf($pageData->publishableComponent ?? self::fail('The page data holds no publishable component.')));
    }
}
