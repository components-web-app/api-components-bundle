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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceDeletion;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDeleter;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataComponentMetadata;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;

class OrphanedResourceDeleterTest extends OrphanedResourceDatabaseTestCase
{
    private OrphanedResourceDeleter $deleter;
    private object $flushCounter;

    protected function setUp(): void
    {
        parent::setUp();

        $usageMetadataFactory = $this->createStub(ComponentUsageMetadataFactory::class);
        $usageMetadataFactory->method('create')->willReturnCallback(fn (ComponentInterface $component) => new ComponentUsageMetadata(
            \count($this->entityManager->getRepository(ComponentPosition::class)->findBy(['component' => $component])),
            0
        ));
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturnCallback(fn (object $component) => [new PageDataComponentMetadata(
            array_merge(
                $this->entityManager->getRepository(PageDataWithComponent::class)->findBy(['component' => $component]),
                $this->entityManager->getRepository(PageDataWithComponent::class)->findBy(['publishableComponent' => $component]),
            ),
            new ArrayCollection(['component', 'publishableComponent'])
        )]);
        $helper = new OrphanedResourceHelper($this->createStub(PageDataMetadataFactoryInterface::class), $usageMetadataFactory, $this->registry, $this->publishableAttributeReader, $pageDataProvider);

        $this->deleter = new OrphanedResourceDeleter($this->registry, $this->detector, $helper, $this->iriConverter);
        $this->flushCounter = new class {
            public int $flushes = 0;

            public function postFlush(PostFlushEventArgs $args): void
            {
                ++$this->flushes;
            }
        };
        $this->entityManager->getEventManager()->addEventListener(Events::postFlush, $this->flushCounter);
    }

    public function test_only_the_selected_orphans_are_deleted(): void
    {
        $selected = $this->persist(new DummyComponent());
        $unselected = $this->persist(new DummyComponent());
        $emptyPosition = $this->position($this->pageGroup());
        $iris = $this->iris($selected, $unselected, $emptyPosition);

        $result = $this->delete([$iris[0], $iris[2]]);

        self::assertSame([$iris[0]], $result->deleted['components']);
        self::assertSame([$iris[2]], $result->deleted['componentPositions']);
        self::assertSame([], $result->rejected);
        self::assertSame([$iris[1]], $this->remaining(DummyComponent::class));
    }

    public function test_all_orphans_are_deleted_when_no_iris_are_given(): void
    {
        $components = [$this->persist(new DummyComponent()), $this->persist(new DummyComponent())];
        $group = $this->group('orphan');
        $iris = $this->iris(...$components);

        $result = $this->delete(null);

        sort($iris);
        self::assertSame($iris, $result->deleted['components']);
        self::assertSame([$this->iri($group)], $result->deleted['componentGroups']);
        self::assertSame([], $this->remaining(DummyComponent::class));
    }

    public function test_a_resource_in_use_is_rejected_and_kept(): void
    {
        $placed = new DummyComponent();
        $this->position($this->pageGroup(), $placed);
        $iri = $this->iris($placed)[0];

        $result = $this->delete([$iri]);

        self::assertSame([['iri' => $iri, 'reason' => OrphanedResourceDeleter::NOT_ORPHANED]], $result->rejected);
        self::assertSame([$iri], $this->remaining(DummyComponent::class));
    }

    public function test_an_iri_that_resolves_to_nothing_is_rejected_as_not_found_and_repeats_are_ignored(): void
    {
        $result = $this->delete(['/nothing/here', '/nothing/here']);

        self::assertSame([['iri' => '/nothing/here', 'reason' => OrphanedResourceDeleter::NOT_FOUND]], $result->rejected);
        self::assertSame(0, $this->flushCounter->flushes);
    }

    public function test_a_group_is_deleted_with_its_positions_and_the_components_only_they_use_each_counted_once(): void
    {
        $group = $this->group('orphan');
        $onlyHere = new DummyComponent();
        $this->position($group, $onlyHere);
        $alsoElsewhere = new DummyComponent();
        $this->position($group, $alsoElsewhere);
        $this->position($this->pageGroup(), $alsoElsewhere);
        $empty = $this->position($group);
        $this->flushAndClear();

        $result = $this->deleter->delete([$this->iri($group), $this->iri($empty)]);

        self::assertSame([$this->iri($group)], $result->deleted['componentGroups']);
        self::assertCount(3, $result->deleted['componentPositions']);
        self::assertSame([$this->iri($onlyHere)], $result->deleted['components']);
        self::assertSame([$this->iri($alsoElsewhere)], $this->remaining(DummyComponent::class));
    }

    public function test_a_deleted_published_component_takes_its_unused_draft_with_it(): void
    {
        $published = $this->persist((new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $iris = $this->iris($published, $draft);

        $result = $this->delete([$iris[0]]);

        sort($iris);
        self::assertSame($iris, $result->deleted['components']);
        self::assertSame([], $this->remaining(DummyPublishableComponent::class));
    }

    public function test_a_draft_that_is_itself_in_use_is_kept(): void
    {
        $published = $this->persist((new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        $placedDraft = (new DummyPublishableComponent())->setPublishedResource($published);
        $this->position($this->pageGroup(), $placedDraft);
        $iris = $this->iris($published, $placedDraft);

        $result = $this->delete([$iris[0]]);

        self::assertSame([$iris[0]], $result->deleted['components']);
        self::assertSame([$iris[1]], $this->remaining(DummyPublishableComponent::class));
    }

    public function test_a_draft_held_by_page_data_is_kept(): void
    {
        $published = $this->persist((new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $pageData = new PageDataWithComponent();
        $pageData->page = $this->page();
        $pageData->publishableComponent = $draft;
        $this->persist($pageData);
        $iris = $this->iris($published, $draft);

        $result = $this->delete([$iris[0]]);

        self::assertSame([$iris[0]], $result->deleted['components']);
        self::assertSame([$iris[1]], $this->remaining(DummyPublishableComponent::class));
    }

    public function test_a_deleted_component_takes_the_groups_it_owns_and_their_contents_with_it(): void
    {
        $owner = $this->persist(new DummyComponent());
        $owned = $this->group('owned');
        $owner->addComponentGroup($owned);
        $nestedOwner = new DummyComponent();
        $this->position($owned, $nestedOwner);
        $nestedGroup = $this->group('nested');
        $nestedOwner->addComponentGroup($nestedGroup);
        $deepest = new DummyComponent();
        $this->position($nestedGroup, $deepest);
        $this->flushAndClear();

        $result = $this->deleter->delete([$this->iri($owner)]);

        $expectedGroups = [$this->iri($owned), $this->iri($nestedGroup)];
        sort($expectedGroups);
        self::assertSame($expectedGroups, $result->deleted['componentGroups']);
        self::assertCount(2, $result->deleted['componentPositions']);
        self::assertCount(3, $result->deleted['components']);
        self::assertSame([], $this->remaining(DummyComponent::class));
    }

    public function test_a_group_shared_with_a_surviving_owner_is_kept(): void
    {
        $owner = $this->persist(new DummyComponent());
        $shared = $this->group('shared');
        $owner->addComponentGroup($shared);
        $this->page()->addComponentGroup($shared);
        $this->flushAndClear();

        $result = $this->deleter->delete([$this->iri($owner)]);

        self::assertSame([], $result->deleted['componentGroups']);
        self::assertNotNull($this->entityManager->find($shared::class, $shared->getId()));
    }

    public function test_a_group_shared_only_by_deleted_owners_is_deleted(): void
    {
        $first = $this->persist(new DummyComponent());
        $second = $this->persist(new DummyComponent());
        $shared = $this->group('shared');
        $first->addComponentGroup($shared);
        $second->addComponentGroup($shared);
        $this->flushAndClear();

        $result = $this->deleter->delete(null);

        self::assertSame([$this->iri($shared)], $result->deleted['componentGroups']);
    }

    public function test_everything_is_deleted_in_one_flush(): void
    {
        $this->group('orphan');
        $this->persist(new DummyComponent());
        $this->position($this->pageGroup());
        $this->flushAndClear();
        $this->flushCounter->flushes = 0;

        $this->deleter->delete(null);

        self::assertSame(1, $this->flushCounter->flushes);
    }

    /**
     * @param list<string>|null $iris
     */
    private function delete(?array $iris): OrphanedResourceDeletion
    {
        $this->flushAndClear();
        $this->flushCounter->flushes = 0;

        return $this->deleter->delete($iris);
    }

    /**
     * @return list<string>
     */
    private function iris(object ...$resources): array
    {
        $this->entityManager->flush();

        return array_map($this->iri(...), $resources);
    }

    /**
     * @return list<string>
     */
    private function remaining(string $class): array
    {
        $this->entityManager->clear();
        $iris = array_map($this->iri(...), $this->entityManager->getRepository($class)->findAll());
        sort($iris);

        return $iris;
    }

    private function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
