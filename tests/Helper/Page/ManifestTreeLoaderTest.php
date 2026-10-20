<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Page;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Page\ManifestTreeLoader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableCustomComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class ManifestTreeLoaderTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_routed_pages_tree_is_loaded_in_two_queries_per_owner_level_and_then_walked_without_any(): void
    {
        $page = $this->page();
        $layout = $this->layout();
        $page->layout = $layout;
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        foreach ([0, 1] as $index) {
            $component = $this->position($pageGroup, new DummyComponent())->component;
            $nestedGroup = $this->group('nested-' . $index);
            $nestedGroup->addComponent($component);
            $this->position($nestedGroup, new DummyComponent());
        }
        $layoutGroup = $this->group('layout-group');
        $layout->addComponentGroup($layoutGroup);
        $this->position($layoutGroup, new DummyComponent());
        $route = $this->persist((new Route())->setPath('/page')->setName('page')->setPage($page));
        $routeId = $this->flushAndClear($route);

        $route = $this->entityManager->find(Route::class, $routeId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($route);

        self::assertCount(8, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        $this->queryLogger->queries = [];
        self::assertSame(['nested-0' => 1, 'nested-1' => 1, 'page-group' => 2], $this->walk($route->getPage()->getComponentGroups()));
        self::assertSame(['layout-group' => 1], $this->walk($route->getPage()->layout->getComponentGroups()));
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_redirected_page_data_route_loads_its_targets_template_and_every_ancestor_page(): void
    {
        $template = $this->page();
        $templateGroup = $this->group('template-group');
        $template->addComponentGroup($templateGroup);
        $this->position($templateGroup, new DummyComponent());
        $parent = $this->page();
        $parentGroup = $this->group('parent-group');
        $parent->addComponentGroup($parentGroup);
        $this->position($parentGroup, new DummyComponent());
        $pageData = new PageData();
        $pageData->page = $template;
        $pageData->setParentPage($parent);
        $this->persist($pageData);
        $target = $this->persist((new Route())->setPath('/target')->setName('target')->setPageData($pageData));
        $redirect = $this->persist((new Route())->setPath('/old')->setName('old')->setRedirect($target));
        $redirectId = $this->flushAndClear($redirect);

        $redirect = $this->entityManager->find(Route::class, $redirectId);
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($redirect);

        $this->queryLogger->queries = [];
        $pageData = $redirect->getRedirect()->getPageData();
        self::assertSame(['template-group' => 1], $this->walk($pageData->page->getComponentGroups()));
        self::assertSame(['parent-group' => 1], $this->walk($pageData->getParentPage()->getComponentGroups()));
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_page_that_is_both_the_template_and_the_parent_of_page_data_is_loaded_once(): void
    {
        $template = $this->page();
        $templateGroup = $this->group('template-group');
        $template->addComponentGroup($templateGroup);
        $this->position($templateGroup, new DummyComponent());
        $pageData = new PageData();
        $pageData->page = $template;
        $pageData->setParentPage($template);
        $this->persist($pageData);
        $route = $this->persist((new Route())->setPath('/page-data')->setName('page-data')->setPageData($pageData));
        $routeId = $this->flushAndClear($route);

        $route = $this->entityManager->find(Route::class, $routeId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($route);

        self::assertCount(5, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        self::assertCount(1, array_filter($this->queryLogger->queries, static fn (string $sql): bool => str_contains($sql, 'LEFT JOIN') && str_contains($sql, 'FROM _acb_page ') && !str_contains($sql, ' CASE ')));
        $this->queryLogger->queries = [];
        self::assertSame(['template-group' => 1], $this->walk($route->getPageData()->page->getComponentGroups()));
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_component_placed_inside_a_group_it_owns_is_loaded_once(): void
    {
        $page = $this->page();
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        $component = $this->position($pageGroup, new DummyComponent())->component;
        $ownGroup = $this->group('own-group');
        $ownGroup->addComponent($component);
        $this->position($ownGroup, $component);
        $pageId = $this->flushAndClear($page);

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($page);

        self::assertCount(4, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function publishableComponentCounts(): iterable
    {
        yield 'two of each publishable class' => [2];
        yield 'seven of each publishable class' => [7];
    }

    #[DataProvider('publishableComponentCounts')]
    public function test_publishable_components_their_drafts_and_the_drafts_groups_load_in_a_number_of_queries_independent_of_how_many_there_are(int $perClass): void
    {
        $page = $this->page();
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        for ($index = 0; $index < $perClass; ++$index) {
            $component = $this->position($pageGroup, (new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')))->component;
            $custom = $this->position($pageGroup, (new DummyPublishableCustomComponent())->setCustomPublishedAt(new \DateTime('-1 day')))->component;
            if (0 === $index % 2) {
                $draft = $this->persist((new DummyPublishableComponent())->setPublishedResource($component));
                $draftGroup = $this->group('draft-group-' . $index);
                $draftGroup->addComponent($draft);
                $this->position($draftGroup, new DummyComponent());
                $this->persist((new DummyPublishableCustomComponent())->setCustomPublishedResource($custom));
            }
        }
        $pageId = $this->flushAndClear($page);

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($page);

        self::assertCount(8, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        $this->queryLogger->queries = [];
        $drafts = 0;
        $draftGroups = [];
        foreach ($page->getComponentGroups()->first()->componentPositions as $position) {
            $component = $position->component;
            $draft = $component instanceof DummyPublishableComponent ? $component->getDraftResource() : $component->getCustomDraftResource();
            if (null === $draft) {
                continue;
            }
            ++$drafts;
            self::assertNull($draft instanceof DummyPublishableComponent ? $draft->getDraftResource() : $draft->getCustomDraftResource());
            $draftGroups += $this->walk($draft->getComponentGroups());
        }
        self::assertSame(2 * intdiv($perClass + 1, 2), $drafts);
        self::assertCount(intdiv($perClass + 1, 2), $draftGroups);
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_draft_placed_in_a_position_of_its_own_is_loaded_without_looking_up_a_draft_of_it(): void
    {
        $page = $this->page();
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        $published = $this->persist((new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        $this->position($pageGroup, (new DummyPublishableComponent())->setPublishedResource($published));
        $pageId = $this->flushAndClear($page);

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($page);

        self::assertCount(5, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        $this->queryLogger->queries = [];
        $draft = $page->getComponentGroups()->first()->componentPositions->first()->component;
        self::assertInstanceOf(DummyPublishableComponent::class, $draft);
        self::assertNull($draft->getDraftResource());
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_without_a_publishable_component_class_each_owner_level_is_one_query(): void
    {
        $page = $this->page();
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        $this->position($pageGroup, new DummyComponent());
        $pageId = $this->flushAndClear($page);
        $metadata = $this->entityManager->getClassMetadata(AbstractComponent::class);
        $metadata->discriminatorMap = array_filter($metadata->discriminatorMap, fn (string $class): bool => !$this->publishableAttributeReader->isConfigured($class));

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader))->load($page);

        self::assertCount(2, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
    }

    public function test_owners_are_loaded_in_chunks_of_the_chunk_size(): void
    {
        $page = $this->page();
        $pageGroup = $this->group('page-group');
        $page->addComponentGroup($pageGroup);
        for ($index = 0; $index < 5; ++$index) {
            $this->position($pageGroup, new DummyComponent());
        }
        for ($index = 0; $index < 3; ++$index) {
            $this->position($pageGroup, (new DummyPublishableComponent())->setPublishedAt(new \DateTime('-1 day')));
        }
        $pageId = $this->flushAndClear($page);

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, $this->publishableAttributeReader, 2))->load($page);

        self::assertCount(12, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        self::assertSame(500, ManifestTreeLoader::CHUNK_SIZE);
    }

    public function test_nothing_is_loaded_without_an_orm_entity_manager(): void
    {
        $page = $this->page();
        $page->addComponentGroup($this->group('page-group'));
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(Page::class)->willReturn($this->createStub(ObjectManager::class));

        (new ManifestTreeLoader($registry, $this->publishableAttributeReader))->load($page);
    }

    private function flushAndClear(object $entity): string
    {
        $this->entityManager->flush();
        $id = (string) $entity->getId();
        $this->entityManager->clear();

        return $id;
    }

    /**
     * @param iterable<ComponentGroup> $groups
     *
     * @return array<string, int> each group reached by reference, with its number of positions
     */
    private function walk(iterable $groups): array
    {
        $reached = [];
        foreach ($groups as $group) {
            $reached[$group->reference] = \count($group->componentPositions);
            foreach ($group->componentPositions as $position) {
                $component = $position->component;
                self::assertInstanceOf(AbstractComponent::class, $component);
                self::assertNull($component->uiComponent);
                $reached += $this->walk($component->getComponentGroups());
            }
        }
        ksort($reached);

        return $reached;
    }
}
