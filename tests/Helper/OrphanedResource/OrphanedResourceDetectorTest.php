<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;

class OrphanedResourceDetectorTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_group_with_no_page_layout_or_component_owner_is_reported(): void
    {
        $orphan = $this->group('orphan');
        $this->page()->addComponentGroup($this->group('in-page'));
        $this->layout()->addComponentGroup($this->group('in-layout'));
        $this->position($this->pageGroup(), new DummyComponent())->component->addComponentGroup($this->group('in-component'));

        self::assertSame([$this->iri($orphan)], $this->detect()->componentGroups);
    }

    public function test_a_position_with_neither_a_component_nor_a_page_data_property_is_reported(): void
    {
        $group = $this->pageGroup();
        $empty = $this->position($group);
        $this->position($group, new DummyComponent());
        $dynamic = $this->position($group);
        $dynamic->pageDataProperty = 'component';

        self::assertSame([$this->iri($empty)], $this->detect()->componentPositions);
    }

    public function test_a_component_in_no_position_and_no_page_data_property_is_reported(): void
    {
        $unused = $this->persist(new DummyComponent());
        $this->position($this->pageGroup(), new DummyComponent());

        self::assertSame([$this->iri($unused)], $this->detect()->components);
    }

    public function test_the_positions_and_components_inside_an_orphaned_group_are_reported(): void
    {
        $orphan = $this->group('orphan');
        $component = $this->persist(new DummyComponent());
        $position = $this->position($orphan, $component);
        $empty = $this->position($orphan);

        $report = $this->detect();

        self::assertSame([$this->iri($orphan)], $report->componentGroups);
        self::assertSame($this->sorted($position, $empty), $report->componentPositions);
        self::assertSame([$this->iri($component)], $report->components);
    }

    public function test_a_chain_through_a_component_owned_group_is_reported_to_the_end(): void
    {
        $outerGroup = $this->group('outer');
        $outerComponent = $this->persist(new DummyComponent());
        $outerPosition = $this->position($outerGroup, $outerComponent);
        $innerGroup = $this->group('inner');
        $outerComponent->addComponentGroup($innerGroup);
        $innerComponent = $this->persist(new DummyComponent());
        $innerPosition = $this->position($innerGroup, $innerComponent);

        $report = $this->detect();

        self::assertSame($this->sorted($outerGroup, $innerGroup), $report->componentGroups);
        self::assertSame($this->sorted($outerPosition, $innerPosition), $report->componentPositions);
        self::assertSame($this->sorted($outerComponent, $innerComponent), $report->components);
    }

    public function test_the_groups_owned_by_an_unused_component_are_reported_with_their_contents(): void
    {
        $owner = $this->persist(new DummyComponent());
        $owned = $this->group('owned');
        $owner->addComponentGroup($owned);
        $ownedComponent = $this->persist(new DummyComponent());
        $ownedPosition = $this->position($owned, $ownedComponent);

        $report = $this->detect();

        self::assertSame([$this->iri($owned)], $report->componentGroups);
        self::assertSame([$this->iri($ownedPosition)], $report->componentPositions);
        self::assertSame($this->sorted($owner, $ownedComponent), $report->components);
    }

    public function test_a_component_in_an_orphaned_group_and_a_live_group_is_not_reported_but_its_orphaned_position_is(): void
    {
        $orphan = $this->group('orphan');
        $shared = $this->persist(new DummyComponent());
        $orphanedPosition = $this->position($orphan, $shared);
        $this->position($this->pageGroup(), $shared);
        $owned = $this->group('owned');
        $shared->addComponentGroup($owned);
        $this->position($owned, new DummyComponent());

        $report = $this->detect();

        self::assertSame([$this->iri($orphan)], $report->componentGroups);
        self::assertSame([$this->iri($orphanedPosition)], $report->componentPositions);
        self::assertSame([], $report->components);
    }

    public function test_a_group_owned_by_an_orphaned_component_and_a_live_page_is_not_reported(): void
    {
        $owner = $this->persist(new DummyComponent());
        $shared = $this->group('shared');
        $owner->addComponentGroup($shared);
        $this->page()->addComponentGroup($shared);
        $this->position($shared, new DummyComponent());

        $report = $this->detect();

        self::assertSame([], $report->componentGroups);
        self::assertSame([], $report->componentPositions);
        self::assertSame([$this->iri($owner)], $report->components);
    }

    public function test_a_group_owned_by_an_orphaned_component_and_a_live_component_is_not_reported(): void
    {
        $owner = $this->persist(new DummyComponent());
        $liveOwner = $this->persist(new DummyComponent());
        $this->position($this->pageGroup(), $liveOwner);
        $shared = $this->group('shared');
        $owner->addComponentGroup($shared);
        $liveOwner->addComponentGroup($shared);

        self::assertSame([], $this->detect()->componentGroups);
    }

    public function test_a_component_in_an_orphaned_group_that_page_data_also_holds_is_not_reported(): void
    {
        $orphan = $this->group('orphan');
        $pageData = new PageDataWithComponent();
        $pageData->page = $this->page();
        $pageData->component = $this->persist(new DummyComponent());
        $this->persist($pageData);
        $parentTyped = new PageDataWithParentTypedComponent();
        $parentTyped->page = $pageData->page;
        $parentTyped->component = $this->persist(new DummyComponent());
        $this->persist($parentTyped);
        $this->position($orphan, $pageData->component);
        $this->position($orphan, $parentTyped->component);

        $report = $this->detect();

        self::assertSame([$this->iri($orphan)], $report->componentGroups);
        self::assertCount(2, $report->componentPositions);
        self::assertSame([], $report->components);
    }

    public function test_a_draft_orphaned_through_its_published_version_is_not_reported_but_the_groups_it_owns_are(): void
    {
        $orphan = $this->group('orphan');
        $published = $this->persist(new DummyPublishableComponent());
        $this->position($orphan, $published);
        $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $draftGroup = $this->group('draft-owned');
        $draft->addComponentGroup($draftGroup);
        $draftGroupComponent = $this->persist(new DummyComponent());
        $draftGroupPosition = $this->position($draftGroup, $draftGroupComponent);

        $report = $this->detect();

        self::assertSame($this->sorted($orphan, $draftGroup), $report->componentGroups);
        self::assertContains($this->iri($draftGroupPosition), $report->componentPositions);
        self::assertSame($this->sorted($published, $draftGroupComponent), $report->components);
    }

    public function test_a_draft_of_a_live_published_component_keeps_the_groups_it_owns(): void
    {
        $published = $this->persist(new DummyPublishableComponent());
        $this->position($this->pageGroup(), $published);
        $orphan = $this->group('orphan');
        $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $this->position($orphan, $draft);
        $draftGroup = $this->group('draft-owned');
        $draft->addComponentGroup($draftGroup);
        $this->position($draftGroup, new DummyComponent());

        $report = $this->detect();

        self::assertSame([$this->iri($orphan)], $report->componentGroups);
        self::assertSame([], $report->components);
    }

    public function test_a_placed_draft_of_an_orphaned_published_component_keeps_the_groups_it_owns(): void
    {
        $published = $this->persist(new DummyPublishableComponent());
        $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $this->position($this->pageGroup(), $draft);
        $draftGroup = $this->group('draft-owned');
        $draft->addComponentGroup($draftGroup);
        $this->position($draftGroup, new DummyComponent());

        $report = $this->detect();

        self::assertSame([], $report->componentGroups);
        self::assertSame([$this->iri($published)], $report->components);
    }

    public function test_a_component_whose_group_holds_the_component_itself_is_reported_when_nothing_else_uses_it(): void
    {
        $orphan = $this->group('orphan');
        $component = $this->persist(new DummyComponent());
        $outerPosition = $this->position($orphan, $component);
        $own = $this->group('own');
        $component->addComponentGroup($own);
        $ownPosition = $this->position($own, $component);

        $report = $this->detect();

        self::assertSame($this->sorted($orphan, $own), $report->componentGroups);
        self::assertSame($this->sorted($outerPosition, $ownPosition), $report->componentPositions);
        self::assertSame([$this->iri($component)], $report->components);
    }

    public function test_a_cycle_with_no_other_link_into_the_tree_is_reported(): void
    {
        $first = $this->persist(new DummyComponent());
        $second = $this->persist(new DummyComponent());
        $firstGroup = $this->group('first');
        $secondGroup = $this->group('second');
        $first->addComponentGroup($firstGroup);
        $second->addComponentGroup($secondGroup);
        $firstPosition = $this->position($firstGroup, $second);
        $secondPosition = $this->position($secondGroup, $first);

        $report = $this->detect();

        self::assertSame($this->sorted($firstGroup, $secondGroup), $report->componentGroups);
        self::assertSame($this->sorted($firstPosition, $secondPosition), $report->componentPositions);
        self::assertSame($this->sorted($first, $second), $report->components);
    }

    public function test_a_component_in_its_own_group_on_a_live_page_is_not_reported(): void
    {
        $component = $this->persist(new DummyComponent());
        $this->position($this->pageGroup(), $component);
        $own = $this->group('own');
        $component->addComponentGroup($own);
        $this->position($own, $component);

        $report = $this->detect();

        self::assertSame([], $report->componentGroups);
        self::assertSame([], $report->componentPositions);
        self::assertSame([], $report->components);
    }

    public function test_a_component_referenced_by_any_page_data_component_property_is_not_reported(): void
    {
        $pageData = new PageDataWithComponent();
        $pageData->page = $this->page();
        $pageData->component = new DummyComponent();
        $pageData->publishableComponent = new DummyPublishableComponent();
        $this->persist($pageData->component);
        $this->persist($pageData->publishableComponent);
        $this->persist($pageData);
        $restricted = new PageDataWithRestrictedComponent();
        $restricted->page = $pageData->page;
        $restricted->restrictedComponent = $this->persist(new RestrictedComponent());
        $this->persist($restricted);

        self::assertSame([], $this->detect()->components);
    }

    public function test_a_draft_is_never_reported_and_its_published_version_is_judged_by_its_own_usage(): void
    {
        $unusedPublished = $this->persist(new DummyPublishableComponent());
        $this->persist((new DummyPublishableComponent())->setPublishedResource($unusedPublished));
        $placedPublished = $this->persist(new DummyPublishableComponent());
        $this->position($this->pageGroup(), $placedPublished);
        $this->persist((new DummyPublishableComponent())->setPublishedResource($placedPublished));

        self::assertSame([$this->iri($unusedPublished)], $this->detect()->components);
    }

    public function test_a_publishable_component_with_no_published_version_is_judged_by_its_own_usage(): void
    {
        $unused = $this->persist(new DummyPublishableComponent());
        $this->position($this->pageGroup(), new DummyPublishableComponent());

        self::assertSame([$this->iri($unused)], $this->detect()->components);
    }

    public function test_each_component_is_reported_as_its_own_class(): void
    {
        $components = [
            $this->persist(new DummyComponent()),
            $this->persist(new DummyNavigationLink()),
            $this->persist(new DummyPublishableComponent()),
            $this->persist(new RestrictedComponent()),
        ];
        $expected = array_map($this->iri(...), $components);
        sort($expected);

        self::assertSame($expected, $this->detect()->components);
    }

    public function test_the_iris_of_each_kind_are_sorted(): void
    {
        $group = $this->pageGroup();
        for ($i = 0; $i < 5; ++$i) {
            $this->group('orphan-' . $i);
            $this->position($group);
            $this->persist(new DummyComponent());
        }

        $report = $this->detect();

        foreach ([$report->componentGroups, $report->componentPositions, $report->components] as $iris) {
            self::assertCount(5, $iris);
            $sorted = $iris;
            sort($sorted);
            self::assertSame($sorted, $iris);
        }
    }

    public function test_the_report_is_dated_when_it_is_detected(): void
    {
        $before = new \DateTimeImmutable();
        $generatedAt = $this->detect()->generatedAt;

        self::assertGreaterThanOrEqual($before->getTimestamp(), $generatedAt->getTimestamp());
        self::assertLessThanOrEqual((new \DateTimeImmutable())->getTimestamp(), $generatedAt->getTimestamp());
    }

    public function test_an_empty_database_gives_an_empty_report(): void
    {
        $report = $this->detect();

        self::assertSame([], $report->componentGroups);
        self::assertSame([], $report->componentPositions);
        self::assertSame([], $report->components);
    }

    public function test_the_report_takes_three_queries_however_many_orphans_there_are(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $published = $this->persist(new DummyPublishableComponent());
            $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
            $this->persist(new DummyComponent());
            $this->group('orphan-' . $i);
        }

        $report = $this->detect();

        self::assertCount(10, $report->components);
        self::assertCount(3, $this->queryLogger->queries);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nestingDepths(): iterable
    {
        yield 'one level' => [1];
        yield 'three levels' => [3];
        yield 'eight levels' => [8];
    }

    #[DataProvider('nestingDepths')]
    public function test_the_report_takes_three_queries_however_deep_the_orphaned_chain_is(int $depth): void
    {
        $group = $this->group('orphan');
        for ($level = 0; $level < $depth; ++$level) {
            $component = $this->persist(new DummyPublishableComponent());
            $this->position($group, $component);
            $this->persist((new DummyPublishableComponent())->setPublishedResource($component));
            $group = $this->group('level-' . $level);
            $component->addComponentGroup($group);
        }

        $report = $this->detect();

        self::assertCount($depth + 1, $report->componentGroups);
        self::assertCount($depth, $report->componentPositions);
        self::assertCount($depth, $report->components);
        self::assertCount(3, $this->queryLogger->queries);
    }

    public function test_the_orphans_are_keyed_by_iri_with_their_class_and_identifier_in_report_order(): void
    {
        $group = $this->group('orphan');
        $position = $this->position($this->pageGroup());
        $component = $this->persist(new DummyPublishableComponent());
        $this->detect();

        self::assertSame(
            [
                'componentGroups' => [$this->iri($group) => [$group::class, (string) $group->getId()]],
                'componentPositions' => [$this->iri($position) => [$position::class, (string) $position->getId()]],
                'components' => [$this->iri($component) => [DummyPublishableComponent::class, (string) $component->getId()]],
            ],
            array_map(static fn (array $orphans) => array_map(static fn (array $reference) => [$reference[0], (string) $reference[1]], $orphans), $this->detector->findOrphans())
        );
    }

    /**
     * @return list<string>
     */
    private function sorted(object ...$resources): array
    {
        $iris = array_map($this->iri(...), $resources);
        sort($iris);

        return $iris;
    }

    private function detect(): OrphanedResourceReport
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        return $this->detector->detect();
    }
}
