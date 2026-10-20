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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Page\ManifestTreeLoader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class ManifestTreeLoaderTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_routed_pages_tree_is_loaded_in_one_query_per_owner_level_and_then_walked_without_any(): void
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
        (new ManifestTreeLoader($this->registry))->load($route);

        self::assertCount(4, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
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
        (new ManifestTreeLoader($this->registry))->load($redirect);

        $this->queryLogger->queries = [];
        $pageData = $redirect->getRedirect()->getPageData();
        self::assertSame(['template-group' => 1], $this->walk($pageData->page->getComponentGroups()));
        self::assertSame(['parent-group' => 1], $this->walk($pageData->getParentPage()->getComponentGroups()));
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
        (new ManifestTreeLoader($this->registry))->load($page);

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
        $pageId = $this->flushAndClear($page);

        $page = $this->entityManager->find(Page::class, $pageId);
        $this->queryLogger->queries = [];
        (new ManifestTreeLoader($this->registry, 2))->load($page);

        self::assertCount(4, $this->queryLogger->queries, implode("\n", $this->queryLogger->queries));
        self::assertSame(500, ManifestTreeLoader::CHUNK_SIZE);
    }

    public function test_nothing_is_loaded_without_an_orm_entity_manager(): void
    {
        $page = $this->page();
        $page->addComponentGroup($this->group('page-group'));
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(Page::class)->willReturn($this->createStub(ObjectManager::class));

        (new ManifestTreeLoader($registry))->load($page);
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
