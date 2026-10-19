<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentPosition;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

class ComponentCycleDetectorTest extends TestCase
{
    use PublishableDatabaseTrait;

    private ComponentCycleDetector $detector;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->detector = new ComponentCycleDetector($this->registry, new PublishableAttributeReader($this->registry));
    }

    public function test_a_component_placed_in_its_own_group_is_a_cycle(): void
    {
        $container = new DummyComponent();
        $group = $this->groupOwnedBy($container);

        self::assertTrue($this->detector->isPlacementCyclic($container, $group));
    }

    public function test_a_component_placed_deep_in_its_own_subtree_is_a_cycle(): void
    {
        $container = new DummyComponent();
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);
        $grandchild = new DummyComponent();
        $this->place($this->groupOwnedBy($child), $grandchild);
        $deepest = $this->groupOwnedBy($grandchild);

        self::assertTrue($this->detector->isPlacementCyclic($container, $deepest));
        self::assertTrue($this->detector->isPlacementCyclic($child, $deepest));
        self::assertFalse($this->detector->isPlacementCyclic(new DummyComponent(), $deepest));
    }

    public function test_a_component_shared_between_sibling_containers_is_not_a_cycle(): void
    {
        $pageGroup = new ComponentGroup();
        (new Page())->addComponentGroup($pageGroup);
        $first = new DummyComponent();
        $second = new DummyComponent();
        $this->place($pageGroup, $first);
        $this->place($pageGroup, $second);
        $shared = new DummyComponent();
        $this->place($this->groupOwnedBy($first), $shared);
        $this->groupOwnedBy($shared);

        self::assertFalse($this->detector->isPlacementCyclic($shared, $this->groupOwnedBy($second)));
        self::assertFalse($this->detector->isPlacementCyclic($first, $this->groupOwnedBy($second)));
    }

    public function test_a_draft_placed_in_its_published_component_subtree_is_a_cycle(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($published), $child);

        self::assertTrue($this->detector->isPlacementCyclic($draft, $this->groupOwnedBy($child)));
    }

    public function test_a_published_component_placed_in_its_draft_subtree_is_a_cycle(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);

        self::assertTrue($this->detector->isPlacementCyclic($published, $this->groupOwnedBy($draft)));
    }

    public function test_a_draft_placed_in_a_group_reached_through_its_published_component_position_is_a_cycle(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $draftGroup = $this->groupOwnedBy($draft);
        $container = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $published);

        self::assertTrue($this->detector->isPlacementCyclic($container, $draftGroup));
    }

    public function test_a_group_with_several_owners_is_walked_through_each_owner(): void
    {
        $ancestor = new DummyComponent();
        $first = new DummyComponent();
        $second = new DummyComponent();
        $this->place($this->groupOwnedBy($ancestor), $second);
        $shared = $this->groupOwnedBy($first);
        $shared->addComponent($second);

        self::assertTrue($this->detector->isPlacementCyclic($ancestor, $shared));
        self::assertTrue($this->detector->isPlacementCyclic($first, $shared));
        self::assertFalse($this->detector->isPlacementCyclic(new DummyComponent(), $shared));
    }

    public function test_a_group_reached_twice_does_not_stop_the_walk(): void
    {
        $first = new DummyComponent();
        $second = new DummyComponent();
        $shared = $this->groupOwnedBy($first);
        $shared->addComponent($second);
        $both = new ComponentGroup();
        $this->place($both, $first);
        $this->place($both, $second);
        $ancestor = new DummyComponent();
        $this->place($this->groupOwnedBy($ancestor), $second);

        self::assertTrue($this->detector->isPlacementCyclic($ancestor, $shared));
    }

    public function test_a_draft_owner_with_no_link_back_from_its_published_component_is_still_itself(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);

        self::assertTrue($this->detector->isPlacementCyclic($draft, $this->groupOwnedBy($draft)));
    }

    public function test_a_published_component_with_no_draft_is_walked_through(): void
    {
        $published = new DummyPublishableComponent();
        $container = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $published);
        $publishedGroup = $this->groupOwnedBy($published);

        self::assertTrue($this->detector->isPlacementCyclic($container, $publishedGroup));
        self::assertFalse($this->detector->isOwnershipCyclic($published, new ComponentGroup()));
    }

    public function test_a_lazy_draft_is_loaded_to_find_its_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $publishedId = $published->getId();
        $draftId = $draft->getId();
        $this->entityManager->clear();
        $lazyPublished = $this->entityManager->getReference(DummyPublishableComponent::class, $publishedId);
        $lazyDraft = $this->entityManager->getReference(DummyPublishableComponent::class, $draftId);
        $group = new ComponentGroup();
        $group->components = new ArrayCollection([$lazyPublished]);

        self::assertTrue($this->entityManager->isUninitializedObject($lazyDraft));
        self::assertTrue($this->detector->isPlacementCyclic($lazyDraft, $group));
    }

    public function test_the_walk_ends_on_a_cycle_already_held(): void
    {
        $container = new DummyComponent();
        $child = new DummyComponent();
        $outer = $this->groupOwnedBy($container);
        $this->place($outer, $child);
        $inner = $this->groupOwnedBy($child);
        $this->place($inner, $container);

        self::assertFalse($this->detector->isPlacementCyclic(new DummyComponent(), $inner));
        self::assertFalse($this->detector->isOwnershipCyclic(new DummyComponent(), $inner));
        self::assertTrue($this->detector->isPlacementCyclic($child, $outer));
    }

    public function test_an_owner_inside_the_group_subtree_is_a_cycle(): void
    {
        $container = new DummyComponent();
        $pageGroup = new ComponentGroup();
        $this->place($pageGroup, $container);
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);
        $grandchild = new DummyComponent();
        $this->place($this->groupOwnedBy($child), $grandchild);

        self::assertTrue($this->detector->isOwnershipCyclic($grandchild, $pageGroup));
        self::assertTrue($this->detector->isOwnershipCyclic($container, $pageGroup));
    }

    public function test_an_owner_outside_the_group_subtree_is_not_a_cycle(): void
    {
        $container = new DummyComponent();
        $pageGroup = new ComponentGroup();
        $this->place($pageGroup, $container);
        $sibling = new DummyComponent();
        $this->place($pageGroup, $sibling);
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);

        self::assertFalse($this->detector->isOwnershipCyclic($sibling, $this->groupOwnedBy($child)));
        self::assertFalse($this->detector->isOwnershipCyclic(new DummyComponent(), $pageGroup));
    }

    public function test_an_owner_held_by_a_position_only_the_group_knows_is_a_cycle(): void
    {
        $owner = new DummyComponent();
        $group = new ComponentGroup();
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $owner;
        $group->addComponentPosition($position);

        self::assertTrue($this->detector->isOwnershipCyclic($owner, $group));
    }

    public function test_the_draft_of_an_owner_inside_the_group_subtree_is_a_cycle(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $pageGroup = new ComponentGroup();
        $this->place($pageGroup, $published);

        self::assertTrue($this->detector->isOwnershipCyclic($draft, $pageGroup));
    }

    public function test_a_draft_and_its_published_component_are_the_same_component_both_ways(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);

        self::assertTrue($this->detector->isSameComponent($draft, $published));
        self::assertTrue($this->detector->isSameComponent($published, $draft));
        self::assertTrue($this->detector->isSameComponent($published, $published));
    }

    public function test_different_components_are_not_the_same_component(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);

        self::assertFalse($this->detector->isSameComponent($draft, new DummyPublishableComponent()));
        self::assertFalse($this->detector->isSameComponent(new DummyComponent(), new DummyComponent()));
    }

    private function groupOwnedBy(AbstractComponent $owner): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->addComponent($owner);

        return $group;
    }

    private function place(ComponentGroup $group, AbstractComponent $component): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $group->addComponentPosition($position);
        $component->addComponentPosition($position);
    }
}
