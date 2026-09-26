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

use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;

class OrphanedResourceDetectorTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_group_with_no_page_layout_or_component_owner_is_reported(): void
    {
        $orphan = $this->group('orphan');
        $this->page()->addComponentGroup($this->group('in-page'));
        $this->layout()->addComponentGroup($this->group('in-layout'));
        $this->persist(new DummyComponent())->addComponentGroup($this->group('in-component'));

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

    public function test_a_position_in_an_orphaned_group_still_counts_as_use(): void
    {
        $orphan = $this->group('orphan');
        $this->position($orphan, new DummyComponent());

        $report = $this->detect();

        self::assertSame([$this->iri($orphan)], $report->componentGroups);
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

    private function detect(): OrphanedResourceReport
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->queryLogger->queries = [];

        return $this->detector->detect();
    }
}
