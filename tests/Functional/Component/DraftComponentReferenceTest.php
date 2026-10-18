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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class DraftComponentReferenceTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;
    use ManifestTrait;

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

    public function test_a_group_created_with_a_drafts_iri_as_its_owner_is_owned_by_the_published_component(): void
    {
        $client = self::adminClient();
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'POST', '/_/component_groups', ['reference' => 'nested', 'location' => 'nested', 'components' => [$pair['draft']]]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeListsExactly('components', [$pair['published']]);
        self::assertSame([$pair['published']], self::ownersOf(self::jsonNode('@id')));
    }

    public function test_a_group_given_a_drafts_iri_as_an_owner_is_owned_by_the_published_component(): void
    {
        $client = self::adminClient();
        $group = self::createGroupWithComponentIris(0);
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'PATCH', $group['group'], ['components' => [$pair['draft']]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$pair['published']], self::ownersOf($group['group']));
    }

    public function test_a_group_created_with_a_never_published_drafts_iri_is_owned_by_the_draft_and_kept_on_publish(): void
    {
        $client = self::adminClient();
        $draft = self::createNeverPublishedDraft();
        self::requestJsonLd($client, 'POST', '/_/component_groups', ['reference' => 'nested', 'location' => 'nested', 'components' => [$draft]]);
        self::assertResponseStatusCodeSame(201);
        $group = self::jsonNode('@id');
        self::assertSame([$draft], self::ownersOf($group));

        self::requestJsonLd($client, 'PATCH', $draft, ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$draft], self::ownersOf($group));
    }

    public function test_a_draft_made_by_an_edit_co_owns_its_published_components_groups_until_it_is_published(): void
    {
        $client = self::adminClient();
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $group = self::newComponentGroup('nested');
        $published->addComponentGroup($group);
        self::persistAndFlush($published, $group);
        $publishedIri = self::iriOf($published);
        $groupIri = self::iriOf($group);

        self::requestJsonLd($client, 'PATCH', $publishedIri, ['reference' => 'edited']);
        self::assertResponseStatusCodeSame(200);
        $draft = self::jsonNode('@id');
        self::assertNotSame($publishedIri, $draft);
        self::assertSame(self::sortedIris([$publishedIri, $draft]), self::ownersOf($groupIri));

        self::requestJsonLd($client, 'PATCH', $draft, ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$publishedIri], self::ownersOf($groupIri));
    }

    public function test_publishing_a_draft_keeps_the_groups_its_published_component_gained_after_the_draft(): void
    {
        $client = self::adminClient();
        $pair = self::createPublishedComponentWithDraft();
        self::requestJsonLd($client, 'POST', '/_/component_groups', ['reference' => 'nested', 'location' => 'nested', 'components' => [$pair['published']]]);
        self::assertResponseStatusCodeSame(201);
        $group = self::jsonNode('@id');
        self::assertSame([$pair['published']], self::ownersOf($group));

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$pair['published']], self::ownersOf($group));
    }

    public function test_a_group_owned_only_by_a_draft_is_owned_by_the_published_component_once_published_and_still_renders(): void
    {
        $client = self::adminClient();
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        $layout = self::newLayout('layout');
        $page = self::newPage();
        $page->layout = $layout;
        $pageGroup = self::newComponentGroup('page-group');
        $page->addComponentGroup($pageGroup);
        $route = self::newRoute('/draft-group')->setPage($page);
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $group = self::newComponentGroup('nested');
        $content = new DummyComponent();
        $position = self::newComponentPosition($group, $content, 0);
        self::persistAndFlush($layout, $page, $pageGroup, $route, $published, self::newComponentPosition($pageGroup, $published, 0), $draft, $group, $content, $position);
        self::addOwnerWithoutTheOrm($group->getId(), $draft->getId());
        $groupIri = self::iriOf($group);
        $publishedIri = self::iriOf($published);
        $draftIri = self::iriOf($draft);
        $positionIri = self::iriOf($position);
        $contentIri = self::iriOf($content);
        self::assertSame([$draftIri], self::ownersOf($groupIri));

        self::requestJsonLd($client, 'PATCH', $draftIri, ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([$publishedIri], self::ownersOf($groupIri));
        self::requestJsonLd(static::createClient(), 'GET', '/_/resource_manifest//draft-group');
        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(0, $groupIri);
        self::assertManifestDepthContains(0, $positionIri);
        self::assertManifestDepthContains(0, $contentIri);
    }

    public function test_a_component_field_written_with_a_drafts_iri_holds_the_published_component(): void
    {
        $client = self::adminClient();
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'POST', '/component/dummy_component_with_relateds', ['related' => $pair['draft']]);

        self::assertResponseStatusCodeSame(201);
        self::assertRelatedIs(self::jsonNode('@id'), $pair['published']);
    }

    public function test_a_component_field_changed_to_a_drafts_iri_holds_the_published_component(): void
    {
        $client = self::adminClient();
        $component = self::createComponentWithRelated();
        $pair = self::createPublishedComponentWithDraft();

        self::requestJsonLd($client, 'PATCH', $component, ['related' => $pair['draft']]);

        self::assertResponseStatusCodeSame(200);
        self::assertRelatedIs($component, $pair['published']);
    }

    public function test_publishing_a_draft_written_to_a_component_field_never_empties_the_field(): void
    {
        $client = self::adminClient();
        $component = self::createComponentWithRelated();
        $pair = self::createPublishedComponentWithDraft();
        self::requestJsonLd($client, 'PATCH', $component, ['related' => $pair['draft']]);
        self::assertResponseStatusCodeSame(200);

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertRelatedIs($component, $pair['published']);
    }

    public function test_publishing_a_never_published_draft_keeps_it_in_a_component_field(): void
    {
        $client = self::adminClient();
        $draft = self::createNeverPublishedDraft();
        $component = self::createComponentWithRelated();
        self::requestJsonLd($client, 'PATCH', $component, ['related' => $draft]);
        self::assertResponseStatusCodeSame(200);
        self::assertRelatedIs($component, $draft);

        self::requestJsonLd($client, 'PATCH', $draft, ['publishedAt' => 'now']);

        self::assertResponseStatusCodeSame(200);
        self::assertRelatedIs($component, $draft);
    }

    private static function createComponentWithRelated(): string
    {
        $component = new DummyComponentWithRelated();
        self::persistAndFlush($component);

        return self::iriOf($component);
    }

    private static function assertRelatedIs(string $componentIri, string $relatedIri): void
    {
        $component = self::findByIri($componentIri);
        self::assertInstanceOf(DummyComponentWithRelated::class, $component);
        self::assertSame($relatedIri, self::iriOf($component->related ?? self::fail('The component field holds no component.')));
    }

    /**
     * @return list<string>
     */
    private static function ownersOf(string $groupIri): array
    {
        $group = self::findByIri($groupIri);
        self::assertNotNull($group);
        $entityManager = self::getEntityManager();
        $joinTable = $entityManager->getClassMetadata(AbstractComponent::class)->getAssociationMapping('componentGroups')->joinTable;
        $ids = $entityManager->getConnection()->fetchFirstColumn(
            \sprintf('SELECT %s FROM %s WHERE %s = ?', $joinTable->joinColumns[0]->name, $joinTable->name, $joinTable->inverseJoinColumns[0]->name),
            [(string) $group->getId()],
        );

        return self::sortedIris(array_map(static fn (string $id): string => self::iriOf($entityManager->find(AbstractComponent::class, $id) ?? self::fail('An owner row names a missing component.')), $ids));
    }

    private static function addOwnerWithoutTheOrm(mixed $groupId, mixed $componentId): void
    {
        $entityManager = self::getEntityManager();
        $joinTable = $entityManager->getClassMetadata(AbstractComponent::class)->getAssociationMapping('componentGroups')->joinTable;
        $entityManager->getConnection()->insert($joinTable->name, [
            $joinTable->joinColumns[0]->name => (string) $componentId,
            $joinTable->inverseJoinColumns[0]->name => (string) $groupId,
        ]);
    }

    /**
     * @param list<string> $iris
     *
     * @return list<string>
     */
    private static function sortedIris(array $iris): array
    {
        sort($iris);

        return $iris;
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
