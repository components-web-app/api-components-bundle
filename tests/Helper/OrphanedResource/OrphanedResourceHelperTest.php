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

use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;

class OrphanedResourceHelperTest extends OrphanedResourceDatabaseTestCase
{
    private OrphanedResourceHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $usageMetadataFactory = $this->createStub(ComponentUsageMetadataFactory::class);
        $usageMetadataFactory->method('create')->willReturnCallback(fn (ComponentInterface $component) => new ComponentUsageMetadata(
            \count($this->entityManager->getRepository(ComponentPosition::class)->findBy(['component' => $component])),
            0
        ));
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);
        $this->helper = new OrphanedResourceHelper($this->createStub(PageDataMetadataFactoryInterface::class), $usageMetadataFactory, $this->registry, $this->publishableAttributeReader, $pageDataProvider);
    }

    public function test_a_group_delete_removes_the_groups_owned_by_the_components_it_cascades_to_and_leaves_no_orphans(): void
    {
        $group = $this->pageGroup();
        $owner = new DummyComponent();
        $this->position($group, $owner);
        $ownedGroup = $this->group('owned');
        $owner->addComponentGroup($ownedGroup);
        $this->position($ownedGroup, new DummyComponent());
        $group = $this->reload($group);

        $this->helper->handleRemovedComponentGroup($group);
        $this->entityManager->flush();

        $this->assertNoOrphans();
        self::assertSame([], $this->entityManager->getRepository(DummyComponent::class)->findAll());
    }

    public function test_a_published_component_removed_by_a_cascade_takes_its_unused_draft_with_it(): void
    {
        $group = $this->group('orphan');
        $published = (new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day'));
        $this->position($group, $published);
        $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $group = $this->reload($group);

        $this->helper->handleRemovedComponentGroup($group);
        $this->entityManager->flush();

        $this->assertNoOrphans();
        self::assertSame([], $this->entityManager->getRepository(DummyPublishableComponent::class)->findAll());
    }

    public function test_a_cascade_keeps_a_draft_that_is_itself_placed(): void
    {
        $group = $this->group('orphan');
        $published = (new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day'));
        $this->position($group, $published);
        $draft = (new DummyPublishableComponent())->setPublishedResource($published);
        $this->position($this->pageGroup(), $draft);
        $group = $this->reload($group);

        $this->helper->handleRemovedComponentGroup($group);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertCount(1, $this->entityManager->getRepository(DummyPublishableComponent::class)->findAll());
    }

    public function test_an_explicitly_deleted_published_component_keeps_its_draft_for_the_api_delete(): void
    {
        $published = $this->persist((new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        $this->persist((new DummyPublishableComponent())->setPublishedResource($published));
        $published = $this->reload($published);

        $this->entityManager->remove($published);
        $this->helper->handleRemovedRootResource($published);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertCount(1, $this->entityManager->getRepository(DummyPublishableComponent::class)->findAll());
    }

    public function test_a_component_group_shared_with_another_owner_survives_the_cascade(): void
    {
        $group = $this->group('orphan');
        $owner = new DummyComponent();
        $this->position($group, $owner);
        $shared = $this->group('shared');
        $owner->addComponentGroup($shared);
        $this->page()->addComponentGroup($shared);
        $group = $this->reload($group);

        $this->helper->handleRemovedComponentGroup($group);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNotNull($this->entityManager->getRepository(ComponentGroup::class)->findOneBy(['reference' => 'shared']));
    }

    private function assertNoOrphans(): void
    {
        $this->entityManager->clear();
        $report = $this->detector->detect();

        self::assertSame([[], [], []], [$report->componentGroups, $report->componentPositions, $report->components]);
    }

    /**
     * @template T of object
     *
     * @param T $resource
     *
     * @return T
     */
    private function reload(object $resource): object
    {
        $this->entityManager->flush();
        $id = $this->entityManager->getClassMetadata($resource::class)->getIdentifierValues($resource);
        $this->entityManager->clear();

        return $this->entityManager->find($resource::class, $id);
    }
}
